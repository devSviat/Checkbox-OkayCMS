<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Core\Languages;
use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;

/** Створення, збереження та обробка фіскальних чеків */
class CheckboxReceiptsHelper extends CheckboxApiHelper
{
    private CheckboxShiftsHelper $shiftsHelper;
    private ?bool $receiptsTableReadable = null;
    private CheckboxPrepaymentHelper $prepaymentHelper;

    public function __construct(CheckboxShiftsHelper $shiftsHelper, CheckboxPrepaymentHelper $prepaymentHelper)
    {
        parent::__construct();
        $this->shiftsHelper = $shiftsHelper;
        $this->prepaymentHelper = $prepaymentHelper;
        $this->shiftsHelper->syncWithHelper($this);
        $this->prepaymentHelper->syncWithHelper($this);
    }

    /**
     * Прив'язує $accessToken і $errors за посиланням до іншого хелпера.
     * @see CheckboxShiftsHelper::syncWithHelper()
     */
    public function syncWithHelper(CheckboxApiHelper $helper): void
    {
        $this->accessToken = &$helper->accessToken;
        $this->errors = &$helper->errors;
    }

    /**
     * Відправляє чек продажу або повернення до Checkbox API і зберігає результат у БД.
     * Якщо зміна закрита — зберігає запис без receipt_id (порожній чек) для повторної обробки.
     * При $isReturn шукає останній чек продажу цього замовлення як related_receipt_id.
     *
     * @param int|null $receiptId ID запису в БД для оновлення (замість створення нового)
     * @return array|object|false
     */
    public function createReceipt(int $orderId, bool $isReturn = false, ?int $receiptId = null)
    {
        $this->clearErrors();

        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            // Перевірка саме на токен: при незаповнених налаштуваннях касира
            // getAccessToken() повертає повідомлення, не чіпаючи errors, — і без
            // цієї умови сюди йшов би дарма кинутий запит у бойовий API.
            if (!empty($this->errors) || empty($this->accessToken)) {
                return $tokenResponse;
            }
        }

        // Замовлення з живим ланцюжком не може отримати ще й повний чек продажу.
        // fiscaliseOrder() цю розвилку тримає, але сюди приходять і напряму —
        // ajax-кнопкою з картки замовлення, а схована кнопка захистом не є:
        // стара вкладка дійде сюди з тим самим правом.
        //
        // Скасований ланцюжок навпаки пускає: після повернення звичайний чек —
        // єдиний спосіб фіскалізувати замовлення, і заборона замкнула б
        // менеджера в глухому куті.
        if (!$isReturn) {
            $chain = $this->orderChainStatus($orderId);
            $chainStatus = $chain === null ? null : (string)$chain['pre_payment_status'];
            if ($chainStatus !== null
                && !in_array($chainStatus, ['CANCELLED', 'PARTIAL_CANCELLED'], true)
            ) {
                // Саме «ланцюжок відкрито», а не «закрито»: тут менеджеру треба
                // назвати дію, а не констатувати відмову протилежним за змістом
                // текстом.
                return (object)[
                    'message' => $this->translations->getTranslation('sviat__checkbox__errors_chain_is_open'),
                ];
            }
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->get($orderId);
        if (!$order) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_find_order')];
        }

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $paymentMethod = $paymentsEntity->findOne(['id' => $order->payment_method_id]);

        // Перевірка йде до гілки закритої зміни: інакше замовлення, яке ніколи не
        // поїде в Checkbox, лишало б по собі порожній запис, який потім ніхто не
        // забере — createReceiptsForPaidOrders() відсіює його за тим самим полем.
        if ($paymentMethod && !empty($paymentMethod->{Init::PAYMENT_SKIP_FIELD})) {
            return (object)[
                'message' => $this->translations->getTranslation('sviat__checkbox__payment_method_dont_send'),
            ];
        }

        // Теж до гілки закритої зміни, і з тієї самої причини, що й PAYMENT_SKIP:
        // заготовку на замовлення без позицій ніхто не забере —
        // createReceiptsForPaidOrders() відсіює такі за кількістю товарів.
        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);
        if ($purchasesEntity->count(['order_id' => $orderId]) === 0) {
            return (object)[
                'message' => $this->translations->getTranslation('sviat__checkbox__errors_find_purchases'),
            ];
        }

        // Зміна закрита — лишаємо запис без receipt_id; чек піде, коли зміну
        // відкриють і замовлення знову потрапить у createReceiptsForPaidOrders().
        //
        // Такий запис на замовлення має бути один. Викликів при закритій зміні
        // буває скільки завгодно — кнопка в адмінці, масова дія, хук оплати, —
        // і без пошуку наявного кожен додавав би ще один рядок.
        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        if ($shiftsEntity->count(['opened' => 1]) === 0) {
            $relatedReceiptId = $isReturn ? $this->findLastSaleReceiptId($orderId) : null;
            $placeholderId = $receiptId ?? $this->findEmptyReceiptId($orderId, $isReturn);

            return $this->saveReceiptToDatabase([], $orderId, $isReturn, $placeholderId, $relatedReceiptId);
        }

        $context = $this->buildOrderContext($orderId, $isReturn);
        if (isset($context['message'])) {
            return (object)$context;
        }

        $paymentType = $paymentMethod->{Init::PAYMENT_TYPE_FIELD} ?? 'CASH';
        $payments = [[
            'type' => is_string($paymentType) ? $paymentType : 'CASH',
            'value' => $context['goodsTotal']
        ]];

        $paymentLabel = $paymentMethod->{Init::PAYMENT_LABEL_FIELD} ?? null;
        if (!empty($paymentLabel) && is_string($paymentLabel)) {
            $payments[0]['label'] = $paymentLabel;
        }

        $orderData = [
            'cashier_name' => $context['cashierName'],
            'goods' => $context['goods'],
            'payments' => $payments,
        ];
        if ($context['email'] !== null) {
            $orderData['delivery'] = ['email' => $context['email']];
        }
        if ($context['footer'] !== '') {
            $orderData['footer'] = $context['footer'];
        }

        $relatedReceiptId = $isReturn ? $this->findLastSaleReceiptId($orderId) : null;

        $response = $this->makeApiRequest('receipts/sell', $orderData, ['method' => 'POST']);
        if (empty($this->errors)) {
            return $this->saveReceiptToDatabase($response, $orderId, $isReturn, $receiptId, $relatedReceiptId);
        }

        // Крон нікому не звітує, а замовлення вже позначене оплаченим: без
        // цього рядка невиставлений чек не лишає сліду ніде.
        $this->logFailure('receipt not fiscalised', [
            'order_id'  => $orderId,
            'is_return' => $isReturn,
        ]);

        return $response;
    }

    /**
     * Чек авансу. Створює ланцюжок і запам'ятовує його relation_id.
     *
     * @param string $source ключ CheckboxPaymentForm — визначає і мітку в чеку,
     *                       і те, чи взагалі потрібна була фіскалізація
     * @return array|object|null
     */
    public function createPrepaymentReceipt(int $orderId, int $advanceKopiyky, string $source)
    {
        if (!CheckboxPaymentForm::isKnown($source)) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_unknown_source')];
        }

        // Другий ланцюжок на те саме замовлення: custom_relation_id щоразу новий,
        // тож Checkbox його прийме, а orderChainStatus() бачитиме лише
        // найсвіжіший — перший лишиться відкритим у ДПС назавжди й не дістанеться
        // навіть поверненню. Скасований ланцюжок новий аванс дозволяє.
        $chain = $this->orderChainStatus($orderId);
        if ($chain !== null
            && !in_array((string)$chain['pre_payment_status'], ['CANCELLED', 'PARTIAL_CANCELLED'], true)
        ) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_chain_exists')];
        }

        // Аванс поверх уже виставленого повного чека продажу — та сама подвійна
        // фіскалізація, лише з іншого боку.
        $receiptRows = $this->entityFactory->get(FiscalReceiptsEntity::class)->find(['order_id' => $orderId]);
        if (CheckboxReceiptSet::hasSaleReceipt($receiptRows)) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_sale_exists')];
        }

        $context = $this->buildOrderContext($orderId, false);
        if (isset($context['message'])) {
            return (object)$context;
        }

        if (!CheckboxPrepaymentRules::advanceIsValid($advanceKopiyky, $context['goodsTotal'])) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_advance_amount')];
        }

        $this->shiftsHelper->openShiftIfNeeded();

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $existingChains = $receiptsEntity->count([
            'order_id'     => $orderId,
            'receipt_type' => FiscalReceiptsEntity::TYPE_PREPAYMENT,
        ]);
        $relationId = CheckboxChainId::build($orderId, $existingChains);

        $payload = [
            'id'                 => $this->uuid4(),
            'cashier_name'       => $context['cashierName'],
            'goods'              => $context['goods'],
            'payments'           => [CheckboxPaymentForm::payment($source, $advanceKopiyky)],
            'custom_relation_id' => $relationId,
        ];
        if ($context['footer'] !== '') {
            $payload['footer'] = $context['footer'];
        }
        if ($context['email'] !== null) {
            $payload['delivery'] = ['email' => $context['email']];
        }

        // Рядок-намір ДО виклику: якщо Checkbox створить ланцюжок, а відповідь
        // не дійде (таймаут, обрив, мовчазний збій SQL при збереженні), аванс
        // буде зафіксовано в ДПС, а ми про нього не знатимемо — і автоматика
        // виставить повний чек продажу зверху. Той самий механізм заготовки вже
        // працює в createReceipt() при закритій зміні.
        $pending = $this->saveReceiptToDatabase(
            [],
            $orderId,
            false,
            null,
            null,
            FiscalReceiptsEntity::TYPE_PREPAYMENT,
            $relationId
        );
        $pendingId = is_array($pending) && isset($pending['id']) ? (int)$pending['id'] : null;

        $response = $this->prepaymentHelper->createChain($payload);
        if (!empty($this->errors) || !is_array($response)) {
            if (is_array($response)) {
                // Checkbox відповів і відмовив — ланцюжка немає, намір знімаємо,
                // інакше він хибно блокував би продаж назавжди.
                $this->dropPendingChain($pendingId);

                return (object)$response;
            }

            // Відповіді немає взагалі: ланцюжок міг створитись. Намір лишаємо і
            // позначаємо стан невідомим — це зупинить автоматику до з'ясування.
            $this->updateChainState($pendingId, CheckboxChainDecision::STATUS_UNKNOWN, null, null);

            return (object)['message' => $this->errors['message'] ?? 'checkbox request failed'];
        }

        // Аванс завжди менший за вартість товарів (advanceIsValid), тож одразу
        // після створення ланцюжок може бути лише частково оплаченим.
        $this->updateChainState($pendingId, 'PARTIAL_PAID', $advanceKopiyky, $context['goodsTotal']);

        return $this->saveReceiptToDatabase(
            $response,
            $orderId,
            false,
            $pendingId,
            null,
            FiscalReceiptsEntity::TYPE_PREPAYMENT,
            (string)($response['pre_payment_relation_id'] ?? $relationId)
        );
    }

    /**
     * Чи можна взагалі читати таблицю чеків.
     *
     * Помилки SQL у цьому проєкті ковтаються: `Entity::find()` віддає порожній
     * масив і при збої, і коли рядків справді немає. Для автоматики це різниця
     * між «нічого не робити» і «виставити ще один фіскальний чек», тож питаємо
     * прямо. Результат кешується на час запиту — крон інакше питав би на кожне
     * замовлення.
     */
    private function receiptsTableIsReadable(): bool
    {
        if ($this->receiptsTableReadable !== null) {
            return $this->receiptsTableReadable;
        }

        // Вибираємо саме ті поля, які оголошує сутність: канарка мусить падати
        // рівно тоді, коли падає Entity::find(). Запит на самий id проходив би
        // завжди — і захист мовчки нічого не захищав би.
        $columns = implode(', ', array_map(
            function ($field) {
                return '`' . $field . '`';
            },
            FiscalReceiptsEntity::getFields()
        ));

        $sl = ServiceLocator::getInstance();
        $sql = $sl->getService(QueryFactory::class)->newSqlQuery();
        $sql->setStatement('SELECT ' . $columns . ' FROM ' . FiscalReceiptsEntity::getTable() . ' LIMIT 1');

        $this->receiptsTableReadable = $sl->getService(Database::class)->query($sql) === true;

        return $this->receiptsTableReadable;
    }

    /** Знімає рядок-намір, коли Checkbox прямо відмовив у створенні ланцюжка. */
    private function dropPendingChain(?int $receiptId): void
    {
        if ($receiptId === null) {
            return;
        }

        $this->entityFactory->get(FiscalReceiptsEntity::class)->delete($receiptId);
    }

    /**
     * Запам'ятовує стан ланцюжка на його рядку.
     *
     * Суми в копійках; залишок не зберігаємо — він завжди total - paid, і два
     * джерела правди тут розійшлися б на першій же неточності.
     */
    private function updateChainState(?int $receiptId, string $status, ?int $paidKopiyky, ?int $totalKopiyky): void
    {
        if ($receiptId === null) {
            return;
        }

        $state = ['chain_status' => $status];
        if ($paidKopiyky !== null) {
            $state['chain_paid_sum'] = $paidKopiyky;
        }
        if ($totalKopiyky !== null) {
            $state['chain_total_sum'] = $totalKopiyky;
        }

        $this->entityFactory->get(FiscalReceiptsEntity::class)->update($receiptId, $state);
    }

    /** Останній ланцюжок замовлення разом з його станом у Checkbox. */
    public function orderChainStatus(int $orderId): ?array
    {
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $chainReceipt = $receiptsEntity->order('id_desc')->findOne([
            'order_id'     => $orderId,
            'receipt_type' => FiscalReceiptsEntity::TYPE_PREPAYMENT,
        ]);
        if (!$chainReceipt || empty($chainReceipt->relation_id)) {
            return null;
        }

        // Стан читаємо зі свого рядка, а не з мережі: цей метод викликається на
        // кожен рендер картки замовлення, і блокуючий HTTP тут коштував би до
        // десяти секунд при недоступному API. Рядок оновлюється в момент кожної
        // нашої дії з ланцюжком, а розійтись він може лише через дію ззовні —
        // для цього є кнопка «Оновити стан». Автоматичної звірки немає: сума
        // чека післяплати однаково береться з Checkbox перед виставленням.
        $paid = $chainReceipt->chain_paid_sum === null ? null : (int)$chainReceipt->chain_paid_sum;
        $total = $chainReceipt->chain_total_sum === null ? null : (int)$chainReceipt->chain_total_sum;

        return [
            'pre_payment_status' => (string)($chainReceipt->chain_status ?: CheckboxChainDecision::STATUS_UNKNOWN),
            'relation_id'        => (string)$chainReceipt->relation_id,
            'paid_sum'           => $paid,
            'total_sum'          => $total,
            // Залишок не зберігаємо: два джерела правди розійшлися б на першій
            // же неточності.
            'left_to_pay'        => ($paid === null || $total === null) ? null : max(0, $total - $paid),
        ];
    }

    /**
     * Питає Checkbox про справжній стан ланцюжка і запам'ятовує його.
     *
     * Єдине місце, де стан ланцюжка йде в мережу поза дією з чеком. Потрібне
     * для випадків, коли ланцюжок змінили ззовні — з кабінету Checkbox або
     * іншої каси, — і наш рядок про це не знає.
     *
     * @return array|null стан після оновлення, або null якщо ланцюжка немає
     */
    public function refreshOrderChainStatus(int $orderId): ?array
    {
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $chainReceipt = $receiptsEntity->order('id_desc')->findOne([
            'order_id'     => $orderId,
            'receipt_type' => FiscalReceiptsEntity::TYPE_PREPAYMENT,
        ]);
        if (!$chainReceipt || empty($chainReceipt->relation_id)) {
            return null;
        }

        $status = $this->prepaymentHelper->chainStatus((string)$chainReceipt->relation_id);
        if ($status === null) {
            // Checkbox не відповів. Наявний стан не чіпаємо: він може бути
            // правдивим, а STATUS_UNKNOWN поверх нього лише сховав би кнопки.
            return $this->orderChainStatus($orderId);
        }

        $this->updateChainState(
            (int)$chainReceipt->id,
            (string)($status['pre_payment_status'] ?? CheckboxChainDecision::STATUS_UNKNOWN),
            isset($status['paid_sum']) ? (int)$status['paid_sum'] : null,
            isset($status['total_sum']) ? (int)$status['total_sum'] : null
        );

        return $this->orderChainStatus($orderId);
    }

    /**
     * Чек післяплати. Суму без потреби не рахуємо: left_to_pay віддає Checkbox,
     * а він заморозив склад товарів у момент чека авансу — локальна арифметика
     * розійдеться, якщо замовлення потім редагували.
     *
     * @param int|null $amountKopiyky null — закрити борг повністю
     * @return array|object|null
     */
    public function createAfterPaymentReceipt(int $orderId, ?int $amountKopiyky, string $source, ?string $label = null)
    {
        if (!CheckboxPaymentForm::isKnown($source)) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_unknown_source')];
        }

        // Саме тут стан беремо з Checkbox, а не зі свого рядка: від залишку
        // залежить сума чека, і Checkbox класифікує чек за збігом суми з
        // left_to_pay. Застаріле значення дало б або відмову, або тихо ще один
        // аванс замість закриття. На рендері картки такий виклик неприйнятний,
        // а тут це одна дія менеджера.
        $chain = $this->refreshOrderChainStatus($orderId);
        if ($chain === null) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_no_chain')];
        }
        if ($chain['pre_payment_status'] !== 'PARTIAL_PAID') {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_chain_closed')];
        }

        $leftToPay = (int)($chain['left_to_pay'] ?? 0);
        $amount = $amountKopiyky === null ? $leftToPay : $amountKopiyky;
        if ($amount <= 0 || $amount > $leftToPay) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_after_payment_amount')];
        }

        $this->shiftsHelper->openShiftIfNeeded();

        $cashier = $this->shiftsHelper->getCashierInfo();
        if (is_array($cashier) && isset($cashier['message'])) {
            return (object)$cashier;
        }

        $payload = [
            'id'           => $this->uuid4(),
            'cashier_name' => is_array($cashier) && isset($cashier['full_name']) ? (string)$cashier['full_name'] : '',
            'payments'     => [CheckboxPaymentForm::payment($source, $amount, $label)],
        ];

        $response = $this->prepaymentHelper->addPayment($chain['relation_id'], $payload);
        if (!empty($this->errors) || !is_array($response)) {
            // При таймауті чи cURL-помилці відповіді немає взагалі, а причина
            // лежить в errors. Без цього назовні пішов би null, і менеджер
            // побачив би порожнечу замість пояснення — і натиснув би ще раз.
            if (is_array($response)) {
                return (object)$response;
            }

            return (object)['message' => $this->errors['message'] ?? 'checkbox request failed'];
        }

        // Тип пишемо за фактом, а не за припущенням: Checkbox сам вирішує за
        // сумою, чи це ще аванс, чи вже закриття, і каже це лише окремим GET.
        // Це єдиний мережевий виклик статусу на гарячому шляху, і він тут
        // виправданий: інакше тип чека був би здогадкою.
        $after = $this->prepaymentHelper->chainStatus($chain['relation_id']);
        $isClosed = is_array($after) && ($after['pre_payment_status'] ?? '') === 'FULL_PAID';

        // Той самий виклик заразом оновлює збережений стан — без цього картка
        // показувала б «чекаємо решту» на вже закритому ланцюжку.
        $chainRow = $this->entityFactory->get(FiscalReceiptsEntity::class)->order('id_desc')->findOne([
            'order_id'     => $orderId,
            'receipt_type' => FiscalReceiptsEntity::TYPE_PREPAYMENT,
        ]);
        if ($chainRow && is_array($after)) {
            $this->updateChainState(
                (int)$chainRow->id,
                (string)($after['pre_payment_status'] ?? CheckboxChainDecision::STATUS_UNKNOWN),
                isset($after['paid_sum']) ? (int)$after['paid_sum'] : null,
                isset($after['total_sum']) ? (int)$after['total_sum'] : null
            );
        }

        return $this->saveReceiptToDatabase(
            $response,
            $orderId,
            false,
            null,
            null,
            $isClosed ? FiscalReceiptsEntity::TYPE_AFTER_PAYMENT : FiscalReceiptsEntity::TYPE_PREPAYMENT,
            $chain['relation_id']
        );
    }

    /**
     * Повернення всього ланцюжка. Окремі чеки повернення тут не підходять: ДПС
     * вимагає анулювати ланцюжок цілком.
     *
     * @return array|object|null
     */
    public function returnOrderChain(int $orderId)
    {
        $chain = $this->orderChainStatus($orderId);
        if ($chain === null) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_no_chain')];
        }

        // Повертати можна лише живий ланцюжок. Невідомий стан — не привід діяти
        // наосліп, а вже скасований дав би другий комплект чеків повернення.
        if (!in_array((string)$chain['pre_payment_status'], ['PARTIAL_PAID', 'FULL_PAID'], true)) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_chain_closed')];
        }

        $this->shiftsHelper->openShiftIfNeeded();
        $cashier = $this->shiftsHelper->getCashierInfo();
        $cashierName = is_array($cashier) && isset($cashier['full_name']) ? (string)$cashier['full_name'] : '';

        $response = $this->prepaymentHelper->returnChain($chain['relation_id'], $cashierName);
        if (!empty($this->errors)) {
            return (object)['message' => $this->errors['message'] ?? 'chain return failed'];
        }

        if (is_array($response)) {
            foreach ($response as $returned) {
                if (is_array($returned)) {
                    $this->saveReceiptToDatabase(
                        $returned,
                        $orderId,
                        true,
                        null,
                        null,
                        FiscalReceiptsEntity::TYPE_RETURN,
                        $chain['relation_id']
                    );
                }
            }
        }

        // Повернення — термінальний стан, назад ланцюжок не відкривається.
        // Без цього запису картка й далі гасила б кнопки продажу.
        $chainRow = $this->entityFactory->get(FiscalReceiptsEntity::class)->order('id_desc')->findOne([
            'order_id'     => $orderId,
            'receipt_type' => FiscalReceiptsEntity::TYPE_PREPAYMENT,
        ]);
        if ($chainRow) {
            $this->updateChainState((int)$chainRow->id, 'CANCELLED', null, null);
        }

        return ['returned' => is_array($response) ? count($response) : 0];
    }

    /**
     * Єдина точка входу автоматики: сама вирішує, що належить замовленню.
     * Три хуки викликають саме її, щоб розвилка не розповзлась по копіях.
     *
     * @return array|object|null
     */
    public function fiscaliseOrder(int $orderId, ?int $emptyReceiptId = null)
    {
        // Автоматика ухвалює рішення за ВІДСУТНІСТЮ чеків, тож нечитабельна
        // таблиця для неї нерозрізненна з «чеків немає» — і вона виставить чек
        // повторно. Саме так і стається у вікні між викладкою файлів і
        // виконанням міграції: сутність оголошує колонки, яких у базі ще немає,
        // і кожен SELECT падає мовчки. Перевірено на живому кроні: чотири
        // дублікати за один прохід.
        if (!$this->receiptsTableIsReadable()) {
            $this->logFailure('fiscalisation skipped: receipts table unreadable', ['order_id' => $orderId]);

            return null;
        }

        $chain = $this->orderChainStatus($orderId);
        $chainStatus = $chain === null ? null : (string)$chain['pre_payment_status'];

        // Тип фільтрується в PHP, а не в запиті: до виконання міграції колонки
        // receipt_type ще немає, вибірка з фільтром по ній мовчки порожня, і це
        // прочиталось би як «чека продажу немає» — тобто дубль чека.
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $hasSaleReceipt = CheckboxReceiptSet::hasSaleReceipt(
            $receiptsEntity->find(['order_id' => $orderId])
        );

        $action = CheckboxChainDecision::forOrder($chainStatus, $hasSaleReceipt);
        if ($action === CheckboxChainDecision::ACTION_AFTER_PAYMENT) {
            // Мітку беремо зі способу оплати замовлення: автоматичний чек має
            // назвати той самий засіб платежу, що й звичайний чек продажу.
            return $this->createAfterPaymentReceipt(
                $orderId,
                null,
                $this->orderPaymentSource($orderId),
                $this->orderPaymentLabel($orderId)
            );
        }
        if ($action === CheckboxChainDecision::ACTION_SALE) {
            return $this->createReceipt($orderId, false, $emptyReceiptId);
        }

        return null;
    }

    /**
     * Джерело коштів для автоматичного закриття боргу береться зі способу оплати
     * замовлення — там уже налаштовані тип і мітка.
     *
     * Публічний, бо картка замовлення підставляє це значення в кнопку
     * післяплати: вгадувати джерело в шаблоні означало б хибний рядок 19 у чеку.
     */
    public function orderPaymentSource(int $orderId): string
    {
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->get($orderId);
        if ($order && !empty($order->payment_method_id)) {
            $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
            $method = $paymentsEntity->findOne(['id' => $order->payment_method_id]);
            $label = $method ? ($method->{Init::PAYMENT_LABEL_FIELD} ?? '') : '';
            if (is_string($label) && stripos($label, 'novapay') !== false) {
                return CheckboxPaymentForm::SOURCE_NOVAPAY;
            }
            $type = $method ? ($method->{Init::PAYMENT_TYPE_FIELD} ?? '') : '';
            if ($type === 'CASH') {
                return CheckboxPaymentForm::SOURCE_CASH;
            }
        }

        return CheckboxPaymentForm::SOURCE_BANK_ACCOUNT;
    }

    /** Мітка засобу оплати, налаштована для способу оплати замовлення. */
    private function orderPaymentLabel(int $orderId): ?string
    {
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->get($orderId);
        if (!$order || empty($order->payment_method_id)) {
            return null;
        }

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $method = $paymentsEntity->findOne(['id' => $order->payment_method_id]);
        $label = $method ? ($method->{Init::PAYMENT_LABEL_FIELD} ?? null) : null;

        return is_string($label) && trim($label) !== '' ? $label : null;
    }

    /**
     * Позиції, сума, касир і футер для чеків замовлення.
     *
     * $isReturn без значення за замовчуванням навмисне: він доходить до
     * позначки is_return на кожній позиції, і забутий аргумент відправив би
     * повернення в Checkbox як продаж — мовчки. Хай краще падає.
     *
     * @return array{goods: array, goodsTotal: int, cashierName: string, footer: string, email: string|null}|array{message: string}
     */
    private function buildOrderContext(int $orderId, bool $isReturn): array
    {
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->get($orderId);
        if (!$order) {
            return ['message' => $this->translations->getTranslation('sviat__checkbox__errors_find_order')];
        }

        $serviceLocator = ServiceLocator::getInstance();
        $ordersHelper = $serviceLocator->getService(OrdersHelper::class);
        $purchases = $ordersHelper->getOrderPurchasesList((int)$order->id);
        if (empty($purchases)) {
            return ['message' => $this->translations->getTranslation('sviat__checkbox__errors_find_purchases')];
        }

        $cashier = $this->shiftsHelper->getCashierInfo();
        if (is_array($cashier) && isset($cashier['message'])) {
            return ['message' => (string)$cashier['message']];
        }

        // Назви товарів завжди українською — та сама причина, що й у createReceipt()
        session_write_close();
        unset($_SESSION['lang_id'], $_SESSION['admin_lang_id']);
        $languagesService = $serviceLocator->getService(Languages::class);
        foreach ($languagesService->getAllLanguages() as $language) {
            if ($language->label == 'ua') {
                $languagesService->setLangId($language->id);
            }
        }

        $productsEntity = $this->entityFactory->get(ProductsEntity::class);
        $variantsEntity = $this->entityFactory->get(VariantsEntity::class);
        $taxGroupsEntity = $this->entityFactory->get(TaxGroupsEntity::class);

        foreach ($purchases as $purchase) {
            $purchase->taxes = $taxGroupsEntity->getProductTaxGroupCodes((int)$purchase->product_id);
            if ($product = $productsEntity->findOne(['id' => $purchase->product_id])) {
                $variant = $variantsEntity->findOne(['id' => $purchase->variant_id]);
                $productName = trim((string)$product->name);
                // Порожню назву не ставимо взагалі: краще знімок із замовлення,
                // ніж порожній рядок у фіскальному чеку. Порожня вона буває,
                // коли для товару немає українського мовного рядка.
                if ($productName !== '') {
                    $purchase->fullProductName = $productName
                        . ($variant && $variant->name ? (' - ' . $variant->name) : '');
                }
            }
        }

        $undiscounted = is_numeric($order->undiscounted_total_price) ? (float)$order->undiscounted_total_price : 0.0;
        $total = is_numeric($order->total_price) ? (float)$order->total_price : 0.0;
        $payload = CheckboxReceiptPayloadBuilder::build($purchases, $undiscounted, $total, $isReturn);

        $receiptText = $this->settings->sviat__checkbox__receipt_text ?? '';
        $footer = is_string($receiptText) && $receiptText !== ''
            ? str_replace('{$order_id}', (string)$orderId, $receiptText)
            : '';

        // Налаштування «як надсилати чек» діє на всі чеки замовлення, не лише на
        // продаж: 0 означає «не надсилати», і чек авансу не має бути винятком.
        $email = null;
        $sendMessageSetting = $this->settings->get('sviat__checkbox__send_message');
        $sendMessageSetting = is_numeric($sendMessageSetting) ? (int)$sendMessageSetting : 0;
        if (in_array($sendMessageSetting, [1, 3, 4], true) && is_string($order->email)) {
            $validated = filter_var(trim($order->email), FILTER_VALIDATE_EMAIL);
            if ($validated !== false) {
                $email = $validated;
            }
        }

        return [
            'goods'       => $payload['goods'],
            'goodsTotal'  => $payload['paymentValue'],
            'cashierName' => is_array($cashier) && isset($cashier['full_name']) ? (string)$cashier['full_name'] : '',
            'footer'      => $footer,
            'email'       => $email,
        ];
    }

    /** Checkbox наполегливо радить власний UUID чека — він же дає слід у їхніх логах. */
    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Cron-задача: знаходить ТТН зі статусом "Отримано" (код 9) і позначає замовлення як оплачені.
     * Обробляються тільки ТТН оновлені після sviat__checkbox__installed_at —
     * це захист від масового виставлення чеків при першому запуску модуля.
     *
     * Позначення замовлення як paid тригерить хук orderMarkedPaid → createReceiptsForPaidOrders,
     * який сам відкриє зміну, перевірить PAYMENT_SKIP та дублікати і створить чек.
     * Замовлення з PAYMENT_SKIP також позначаються як paid, але чек до Checkbox не надсилається.
     *
     * Перевірку активності модуля і налаштувань виконує CheckboxHelper::processReceivedOrders().
     */
    public function processReceivedOrders()
    {
        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        $trackings = $trackingEntity->find(['status_code' => '9']);

        if (empty($trackings)) {
            return false;
        }

        $installedAt = $this->settings->get('sviat__checkbox__installed_at');
        if (!empty($installedAt)) {
            $installedTimestamp = strtotime($installedAt);
            if ($installedTimestamp !== false) {
                $trackings = array_filter($trackings, function ($tracking) use ($installedTimestamp) {
                    return !empty($tracking->updated_at) && strtotime($tracking->updated_at) >= $installedTimestamp;
                });
            }
        }

        if (empty($trackings)) {
            return false;
        }

        $orderIds = [];
        foreach ($trackings as $tracking) {
            if (!empty($tracking->order_id)) {
                $orderIds[] = (int)$tracking->order_id;
            }
        }
        $orderIds = array_unique($orderIds);

        if (empty($orderIds)) {
            return false;
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);
        $orders = $ordersEntity->mappedBy('id')->find(['id' => $orderIds]);

        // Відбираємо тільки замовлення що існують і мають товари
        $qualifyingIds = [];
        foreach ($orders as $order) {
            if ($purchasesEntity->count(['order_id' => $order->id]) > 0) {
                $qualifyingIds[] = (int)$order->id;
            }
        }

        if (empty($qualifyingIds)) {
            return false;
        }

        // Позначаємо замовлення як оплачені (paid=1)
        foreach ($qualifyingIds as $orderId) {
            $ordersEntity->update($orderId, (object)['paid' => 1]);
        }

        // Відразу формуємо чеки — не через хук, бо ми вже в контексті cron
        $this->createReceiptsForPaidOrders($qualifyingIds, 1);
    }

    /**
     * Відправляє чеки для щойно оплачених замовлень.
     * Пропускає замовлення без товарів, з PAYMENT_SKIP і з уже існуючим чеком продажу.
     * Відкриває зміну тільки якщо є хоча б одне замовлення для фіскалізації.
     *
     * @param array $ids    ID замовлень
     * @param int   $status 0 = оплата знята, решта = оплачено
     */
    public function createReceiptsForPaidOrders(array $ids, int $status)
    {
        if ($status === 0 || empty($ids)) {
            return false;
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);

        $orders = $ordersEntity->mappedBy('id')->find(['id' => array_map('intval', $ids)]);

        $purchasesCounts = [];
        foreach ($orders as $order) {
            $purchasesCounts[$order->id] = $purchasesEntity->count(['order_id' => $order->id]);
        }

        $paymentMethodIds = [];
        foreach ($orders as $order) {
            if (!empty($order->payment_method_id)) {
                $paymentMethodIds[] = (int)$order->payment_method_id;
            }
        }
        $paymentMethods = [];
        if (!empty($paymentMethodIds)) {
            $paymentMethods = $paymentsEntity->mappedBy('id')->find(['id' => array_unique($paymentMethodIds)]);
        }

        // Перевіряємо існування фіскалізованого чека продажу, а не "тип останнього чека":
        // інакше після створення чека повернення останній чек буде з is_return=1
        // і система помилково повторно випустить новий чек продажу.
        $receipts = $receiptsEntity->find(['order_id' => array_keys($orders)]);
        $hasSaleReceiptMap = [];
        $emptyReceiptMap = [];
        foreach ($receipts as $receipt) {
            $orderId = (int)$receipt->order_id;
            $type = $receipt->receipt_type ?? FiscalReceiptsEntity::TYPE_SALE;

            if ($type === FiscalReceiptsEntity::TYPE_SALE && !empty($receipt->receipt_id)) {
                $hasSaleReceiptMap[$orderId] = true;
            }
            // Заготовку можна віддавати лише під чек продажу — порожній запис
            // повернення чи ланцюжка має власний шлях.
            if (empty($receipt->receipt_id) && $type === FiscalReceiptsEntity::TYPE_SALE) {
                if (!isset($emptyReceiptMap[$orderId]) || $receipt->id > $emptyReceiptMap[$orderId]->id) {
                    $emptyReceiptMap[$orderId] = $receipt;
                }
            }
        }

        // orderId => ID порожнього запису в БД (null якщо немає) — для оновлення замість дублювання
        $ordersToReceipt = [];
        foreach ($orders as $order) {
            $orderId = (int)$order->id;
            if (empty($purchasesCounts[$orderId])) {
                continue;
            }
            if (!empty($order->payment_method_id) && isset($paymentMethods[$order->payment_method_id])) {
                if (!empty($paymentMethods[$order->payment_method_id]->{Init::PAYMENT_SKIP_FIELD})) {
                    continue;
                }
            }
            if (!empty($hasSaleReceiptMap[$orderId])) {
                continue; // вже є фіскалізований чек продажу
            }
            $emptyReceiptId = isset($emptyReceiptMap[$orderId]) ? (int)$emptyReceiptMap[$orderId]->id : null;
            $ordersToReceipt[$orderId] = $emptyReceiptId;
        }

        if (empty($ordersToReceipt)) {
            return false;
        }

        // Відкриваємо зміну тільки якщо є замовлення для фіскалізації
        $this->shiftsHelper->openShiftIfNeeded();

        foreach ($ordersToReceipt as $orderId => $emptyReceiptId) {
            $this->fiscaliseOrder((int)$orderId, $emptyReceiptId);
        }
    }

    /**
     * Зберігає або оновлює запис чека в БД.
     * Якщо $response порожній — зберігає запис без receipt_id (чек відправлений не був).
     *
     * @param int|null    $receiptId       ID запису для оновлення (якщо null — шукає за receipt_id або створює новий)
     * @param string|null $relatedReceiptId receipt_id оригінального чека продажу (для повернення)
     */
    private function saveReceiptToDatabase(
        array $response,
        int $orderId,
        bool $isReturn = false,
        ?int $receiptId = null,
        ?string $relatedReceiptId = null,
        ?string $receiptType = null,
        ?string $relationId = null
    ): ?array {
        $receipt = new \stdClass();
        $receipt->order_id = $orderId;
        $receipt->is_return = (int)$isReturn;
        // Тип лишається похідним від $isReturn доти, доки викликач не назве його
        // прямо: наявні виклики передають лише п'ять аргументів.
        $receipt->receipt_type = $receiptType
            ?? ($isReturn ? FiscalReceiptsEntity::TYPE_RETURN : FiscalReceiptsEntity::TYPE_SALE);
        if ($relationId !== null) {
            $receipt->relation_id = $relationId;
        }

        if (!empty($response['id'])) {
            $receipt->receipt_id = (string)$response['id'];
            // Хост тестової і бойової каси однаковий, тож без цієї позначки
            // тестовий чек у списку нічим не відрізнити від справжнього.
            $receipt->is_test = !empty($response['is_test']) ? 1 : 0;

            $createdAt = strtotime($response['created_at'] ?? '');
            $receipt->created_at = $createdAt !== false ? date('Y-m-d H:i:s', $createdAt) : null;

            $updatedAt = strtotime($response['updated_at'] ?? '');
            $receipt->updated_at = $updatedAt !== false ? date('Y-m-d H:i:s', $updatedAt) : null;

            if ($isReturn && !empty($relatedReceiptId)) {
                $receipt->related_receipt_id = $relatedReceiptId;
            }
        }

        $filter = [];
        if ($receiptId !== null) {
            $filter['id'] = $receiptId;
        } elseif (isset($receipt->receipt_id)) {
            $filter['receipt_id'] = $receipt->receipt_id;
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $existingReceipt = !empty($filter) ? $receiptsEntity->findOne($filter) : null;

        if ($existingReceipt) {
            $receiptsEntity->update($existingReceipt->id, $receipt);
            $receiptId = $existingReceipt->id;
        } else {
            $receiptId = $receiptsEntity->add($receipt);
        }

        if ($receiptId) {
            $savedReceipt = $receiptsEntity->findOne(['id' => $receiptId]);
            return $savedReceipt ? (array)$savedReceipt : null;
        }

        return null;
    }

    /**
     * Запис без receipt_id, який уже чекає на відправку для цього замовлення.
     *
     * Продаж і повернення розділені: порожній запис повернення не можна
     * віддавати під чек продажу.
     *
     * Порядок `id_desc` обов'язковий: замовлення може тягнути хвіст старих
     * порожніх записів, і `createReceiptsForPaidOrders()` бере з нього
     * найновіший. Дефолтний порядок Entity дав би найстаріший — два шляхи
     * оновлювали б різні рядки того самого замовлення.
     */
    private function findEmptyReceiptId(int $orderId, bool $isReturn): ?int
    {
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $existing = $receiptsEntity->order('id_desc')->findOne([
            'order_id'     => $orderId,
            'receipt_type' => $isReturn ? FiscalReceiptsEntity::TYPE_RETURN : FiscalReceiptsEntity::TYPE_SALE,
            'receipt_id'   => '',
        ]);

        return $existing ? (int)$existing->id : null;
    }

    /** Повертає receipt_id останнього чека продажу для замовлення (використовується у чеках повернення) */
    private function findLastSaleReceiptId(int $orderId): ?string
    {
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $lastSaleReceipt = $receiptsEntity->order('id_desc')->findOne([
            'order_id'     => $orderId,
            'receipt_type' => FiscalReceiptsEntity::TYPE_SALE,
        ]);
        return ($lastSaleReceipt && !empty($lastSaleReceipt->receipt_id)) ? $lastSaleReceipt->receipt_id : null;
    }
}
