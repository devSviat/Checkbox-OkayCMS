<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Extenders;

use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxCardActions;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptSet;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentSources;
use Okay\Modules\Sviat\Checkbox\Init\Init;

/** Хуки адмін-панелі: чеки замовлень, зміни, податки, способи оплати */
class BackendExtender implements ExtensionInterface
{
    private Design $design;
    private Request $request;
    private Settings $settings;
    private EntityFactory $entityFactory;
    private CheckboxHelper $checkboxHelper;
    private BackendTranslations $backendTranslations;

    public function __construct(
        Design $design,
        Request $request,
        Settings $settings,
        EntityFactory $entityFactory,
        CheckboxHelper $checkboxHelper,
        BackendTranslations $backendTranslations
    ) {
        $this->design = $design;
        $this->request = $request;
        $this->settings = $settings;
        $this->entityFactory = $entityFactory;
        $this->checkboxHelper = $checkboxHelper;
        $this->backendTranslations = $backendTranslations;
    }

    /**
     * Хук головної сторінки адмінки: авторизує токен, визначає активну зміну,
     * передає в шаблон кількість порожніх чеків.
     */
    public function initializeFiscalReceipt(): void
    {
        // Раніше тут стояв getAccessToken(). Хук висить на evensCounters, тобто
        // виконується на КОЖНІЙ сторінці адмінки, і кожна з них чекала на
        // блокуючий HTTPS-логін у Checkbox — 480 мс із ~500 мс усієї сторінки.
        // Токен тут не потрібен: усі методи CheckboxShiftsHelper беруть його
        // самі й лише коли справді йдуть в API.
        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        $activeShift = $shiftsEntity->getActiveShift();
        if ($activeShift) {
            if ($activeShift->status === 'CREATED') {
                $this->checkboxHelper->checkShiftStatus($activeShift->shift_id);
                $activeShift = $shiftsEntity->getActiveShift();
            }
            $this->design->assign('checkboxActiveShift', $activeShift);
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $this->design->assign('emptyReceiptsCount', $receiptsEntity->count(['receipt_id' => '']));
    }

    /**
     * Хук сторінки замовлення: передає в шаблон чеки замовлення
     * і прапорець "не відправляти до Checkbox" для способу оплати.
     */
    public function initializeOrderReceipts($order, int $orderId): void
    {
        // BackendOrdersHelper::findOrder() віддає false для неіснуючого
        // замовлення — наприклад, коли менеджер відкриває картку за старим
        // посиланням. У PHP 8 звернення до властивості false дає Warning,
        // а той псує заголовки відповіді.
        if (empty($order)) {
            return;
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $orderReceiptRows = $receiptsEntity->find(['order_id' => $order->id]);
        $this->design->assign('orderReceipts', $orderReceiptRows);
        // Рахуються лише заготовки продажу й повернення: намір ланцюжка теж має
        // порожній receipt_id, але це попередження ховає всі дії на картці, і
        // намір потрапив би сюди саме тоді, коли дії найпотрібніші.
        $this->design->assign('emptyOrderReceiptsCount', CheckboxReceiptSet::countUnfinished($orderReceiptRows));

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $orderPaymentMethod = !empty($order->payment_method_id)
            ? $paymentsEntity->findOne(['id' => $order->payment_method_id])
            : null;

        $dontSend = $orderPaymentMethod && !empty($orderPaymentMethod->{Init::PAYMENT_SKIP_FIELD});
        $this->design->assign('orderCheckboxDontSend', $dontSend);

        // Стан читається зі збереженого рядка, а не рахується з наших чеків: чек
        // передплати й чек післяплати повертаються з однаковим type, тож
        // відрізнити їх локально неможливо — джерелом лишається Checkbox, лише
        // опитаний у момент дії, а не на рендері.
        $chain = $this->checkboxHelper->orderChainStatus((int)$order->id);
        $this->design->assign('checkboxChain', $chain);

        $chainStatus = $chain === null ? null : (string)$chain['pre_payment_status'];

        // Тип фільтрується в PHP, а не в запиті: до виконання міграції колонки
        // receipt_type ще немає, і вибірка з фільтром по ній мовчки порожня.
        //
        // Два різні питання про той самий набір чеків: «чи фіскалізували колись»
        // і «чи лишилось що повертати». Перше вирішує, чи можна виставити ще
        // один чек продажу або аванс, друге — чи є що повертати. Плутанина між
        // ними вже давала і глухі кнопки, і зниклі.
        $hasSaleReceipt = CheckboxReceiptSet::hasSaleReceipt($orderReceiptRows);
        $hasUncoveredSale = CheckboxReceiptSet::hasUncoveredSaleReceipt($orderReceiptRows);

        // «Не відправляти чек» — це про спосіб оплати замовлення, тобто про те,
        // як надійде основна сума. Аванс може прийти зовсім іншим шляхом, і
        // менеджер називає його явно, тож прапорець ланцюжка не стосується:
        // ані створення, ані закриття. Він гасить лише чеки, що спираються на
        // сам спосіб оплати — продаж і повернення.
        $this->design->assign('checkboxActions', CheckboxCardActions::forOrder(
            $chainStatus,
            $hasSaleReceipt,
            $hasUncoveredSale,
            (bool)$dontSend,
            (bool)$order->paid
        ));
        // Пункт списку — це рівно та мітка, яка надрукується в рядку 19 чека.
        // Окремої «назви для менеджера» немає навмисне: два різні написи на
        // один вибір змушували читати обидва, а фіскальний наслідок має один.
        $sourcesConfig = CheckboxPaymentSources::decode($this->settings->get(Init::ADVANCE_SOURCES));
        $sources = CheckboxPaymentSources::visible($sourcesConfig);
        $this->design->assign('checkboxSources', $sources);

        // Технічний статус з API — не текст для людини. Поруч із ним іде рядок
        // «що робити далі»: без нього менеджер бачить стан, але не дію.
        $statusTexts = [
            'PARTIAL_PAID'      => $this->backendTranslations->getTranslation('sviat__checkbox__chain_status_partial_paid'),
            'FULL_PAID'         => $this->backendTranslations->getTranslation('sviat__checkbox__chain_status_full_paid'),
            'CANCELLED'         => $this->backendTranslations->getTranslation('sviat__checkbox__chain_status_cancelled'),
            'PARTIAL_CANCELLED' => $this->backendTranslations->getTranslation('sviat__checkbox__chain_status_partial_cancelled'),
            'unknown'           => $this->backendTranslations->getTranslation('sviat__checkbox__chain_status_unknown'),
        ];
        $nextSteps = [
            'PARTIAL_PAID'      => $this->backendTranslations->getTranslation('sviat__checkbox__chain_next_partial_paid'),
            'FULL_PAID'         => $this->backendTranslations->getTranslation('sviat__checkbox__chain_next_full_paid'),
            'CANCELLED'         => $this->backendTranslations->getTranslation('sviat__checkbox__chain_next_cancelled'),
            'PARTIAL_CANCELLED' => $this->backendTranslations->getTranslation('sviat__checkbox__chain_next_cancelled'),
            'unknown'           => $this->backendTranslations->getTranslation('sviat__checkbox__chain_next_unknown'),
        ];

        // Невідомий статус із API краще показати як є, ніж порожнім місцем.
        $this->design->assign(
            'checkboxChainStatusText',
            $chainStatus === null ? '' : ($statusTexts[$chainStatus] ?? $chainStatus)
        );
        $this->design->assign(
            'checkboxChainNextStep',
            $chainStatus === null ? '' : ($nextSteps[$chainStatus] ?? '')
        );

        // Скільки лишилось узяти з клієнта при отриманні. Ім'я навмисне
        // нейтральне: форма накладної підставляє це значення, не знаючи ні про
        // Checkbox, ні про ланцюжок передплати.
        $this->design->assign(
            'orderAmountDueOnPickup',
            ($chain !== null && $chainStatus === 'PARTIAL_PAID' && !empty($chain['left_to_pay']))
                ? round((int)$chain['left_to_pay'] / 100, 2)
                : null
        );

        // Тестова й бойова каси відрізняються лише обліковими даними касира, і
        // з налаштувань режим не видно. Єдиний чесний сигнал — ознака, яку
        // Checkbox повернув на останньому виданому чеку. Вона запізнюється на
        // один чек після зміни облікових даних, але не вгадує.
        $isTestCashier = false;
        foreach ($receiptsEntity->order('id_desc')->find(['page' => 1, 'limit' => 20]) as $recent) {
            if (!empty($recent->receipt_id)) {
                $isTestCashier = !empty($recent->is_test);
                break;
            }
        }
        $this->design->assign('checkboxIsTestCashier', $isTestCashier);

        // Рішення R4: джерело коштів для кнопки післяплати рахує PHP. Шаблон його
        // не вгадує — помилка тут стала б хибним рядком 19 у фіскальному чеку.
        //
        // Виведене зі способу оплати джерело магазин міг прибрати зі списку. Тоді
        // жоден пункт не позначено обраним, select віддає порожнє значення, і
        // кнопка післяплати відмовляє без видимої причини — тож підставляємо
        // перше з наявних.
        $afterPaymentSource = $this->checkboxHelper->orderPaymentSource((int)$order->id);
        if (!in_array($afterPaymentSource, array_column($sources, 'key'), true)) {
            $afterPaymentSource = $sources[0]['key'] ?? '';
        }
        $this->design->assign('checkboxAfterPaymentSource', $afterPaymentSource);
    }

    /** Хук картки товару: передає всі групи ПДВ і прив'язані до товару. */
    public function getProduct($product): void
    {
        $taxGroupsEntity = $this->entityFactory->get(TaxGroupsEntity::class);
        $this->design->assign('checkboxTaxes', $taxGroupsEntity->find());

        // На створенні товару $product - порожній stdClass без id. Звертання до
        // нього друкує попередження в тіло відповіді, після чого жоден заголовок
        // уже не виставити.
        $productId = (int)($product->id ?? 0);
        $this->design->assign(
            'checkboxProductTaxes',
            $productId > 0 ? $taxGroupsEntity->getProductTaxGroups($productId) : []
        );
    }

    /** Хук збереження товару: синхронізує прив'язку товару до груп ПДВ. */
    public function postProduct($product): void
    {
        $taxGroupsEntity = $this->entityFactory->get(TaxGroupsEntity::class);
        // Без типу навмисно: 'array' у Request::post типом не є. Будь-який
        // непорожній $type згортає масив до першого елемента, тож звідси
        // приходив рядок, далі не проходив перевірку is_array — і збереження
        // товару стирало всі його групи ПДВ, нічого не записавши натомість.
        $productTaxes = $this->request->post('checkboxTaxes');
        if (!is_array($productTaxes)) {
            $productTaxes = [];
        }

        $productId = (int)($product->id ?? 0);
        if ($productId <= 0) {
            return;
        }

        $taxGroupsEntity->deleteProductTaxGroups($productId);
        foreach ($productTaxes as $productTax) {
            $taxId = is_numeric($productTax) ? (int)$productTax : 0;
            if ($taxId > 0) {
                $taxGroupsEntity->addProductTaxGroup($productId, $taxId);
            }
        }
    }

    /** Хук списку замовлень: масово підвантажує останній чек для кожного замовлення. */
    public function findOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $orderIds = array_map(fn($o) => (int)$o->id, $orders);
        $receipts = $receiptsEntity->find(['order_id' => $orderIds]);

        $receiptsMap = [];
        foreach ($receipts as $receipt) {
            $orderId = (int)$receipt->order_id;
            if (!isset($receiptsMap[$orderId]) || $receipt->id > $receiptsMap[$orderId]->id) {
                $receiptsMap[$orderId] = $receipt;
            }
        }

        foreach ($orders as $order) {
            $order->receipt = $receiptsMap[(int)$order->id] ?? null;
        }
    }

    /** Хук збереження способу оплати: зберігає Checkbox-поля типу, мітки і skip-прапорець. */
    public function postPayment($paymentMethod): void
    {
        $paymentMethod->{Init::PAYMENT_TYPE_FIELD} = $this->request->post(Init::PAYMENT_TYPE_FIELD);
        $paymentMethod->{Init::PAYMENT_LABEL_FIELD} = $this->request->post(Init::PAYMENT_LABEL_FIELD);
        $paymentMethod->{Init::PAYMENT_SKIP_FIELD} = $this->request->post(Init::PAYMENT_SKIP_FIELD) ? 1 : 0;
    }

    /** Хук зміни оплаченості замовлення: створює чеки для щойно оплачених замовлень. */
    public function orderMarkedPaid($output, array $ids, int $state): void
    {
        $this->checkboxHelper->createReceiptsForPaidOrders($ids, $state);
    }

    /**
     * Хук зміни статусу одного замовлення: відправляє чек якщо статус збігається
     * з налаштованим sviat__checkbox__order_status_id і ще немає чека продажу.
     */
    public function updateOrderStatus($resultUpdate, $order, int $orderStatusId): void
    {
        $statusId = $this->settings->get('sviat__checkbox__order_status_id');
        $checkboxStatusId = is_numeric($statusId) ? (int)$statusId : 0;
        if ($checkboxStatusId === 0 || $checkboxStatusId !== $orderStatusId) {
            return;
        }

        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        if (!$shiftsEntity->getActiveShift()) {
            return;
        }

        if (!empty($order->payment_method_id)) {
            $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
            $paymentMethod = $paymentsEntity->findOne(['id' => $order->payment_method_id]);
            if ($paymentMethod && !empty($paymentMethod->{Init::PAYMENT_SKIP_FIELD})) {
                return;
            }
        }

        // Перевіряємо існування саме чека продажу з receipt_id, а не "тип останнього чека":
        // після створення чека повернення останній чек має is_return=1 і колишня логіка
        // помилково запускала повторне виставлення чека продажу.
        // Тип фільтрується в PHP, а не в запиті: до виконання міграції колонки
        // receipt_type ще немає, вибірка з фільтром по ній мовчки порожня, і це
        // прочиталось би як «чека продажу немає» — тобто дубль чека.
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $saleReceipts = $receiptsEntity->find(['order_id' => $order->id]);
        if (!CheckboxReceiptSet::hasSaleReceipt($saleReceipts)) {
            $this->checkboxHelper->fiscaliseOrder((int)$order->id);
        }
    }

    /**
     * Хук масової зміни статусу замовлень: відправляє чеки для замовлень,
     * у яких встановлено потрібний статус і ще немає чека продажу.
     */
    public function changeStatus($output, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $statusId = $this->settings->get('sviat__checkbox__order_status_id');
        $checkboxStatusId = is_numeric($statusId) ? (int)$statusId : 0;
        if ($checkboxStatusId === 0) {
            return;
        }

        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        if (!$shiftsEntity->getActiveShift()) {
            return;
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $orders = $ordersEntity->mappedBy('id')->find(['id' => array_map('intval', $ids)]);
        if (empty($orders)) {
            return;
        }

        $ordersToProcess = array_filter($orders, fn($o) => (int)$o->status_id === $checkboxStatusId);
        if (empty($ordersToProcess)) {
            return;
        }

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $paymentMethodIds = array_filter(array_map(fn($o) => (int)($o->payment_method_id ?? 0), $ordersToProcess));
        $paymentMethods = !empty($paymentMethodIds)
            ? $paymentsEntity->mappedBy('id')->find(['id' => array_unique($paymentMethodIds)])
            : [];

        // Перевіряємо існування саме чека продажу з receipt_id, а не "тип останнього чека":
        // після створення чека повернення останній чек має is_return=1 і колишня логіка
        // помилково запускала повторне виставлення чека продажу.
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $receipts = $receiptsEntity->find(['order_id' => array_keys($ordersToProcess)]);
        $receiptsByOrder = [];
        foreach ($receipts as $receipt) {
            $receiptsByOrder[(int)$receipt->order_id][] = $receipt;
        }
        $hasSaleReceiptMap = [];
        foreach ($receiptsByOrder as $receiptOrderId => $orderReceipts) {
            if (CheckboxReceiptSet::hasSaleReceipt($orderReceipts)) {
                $hasSaleReceiptMap[$receiptOrderId] = true;
            }
        }

        foreach ($ordersToProcess as $order) {
            if (!empty($order->payment_method_id) && isset($paymentMethods[$order->payment_method_id])) {
                if (!empty($paymentMethods[$order->payment_method_id]->{Init::PAYMENT_SKIP_FIELD})) {
                    continue;
                }
            }
            if (empty($hasSaleReceiptMap[(int)$order->id])) {
                try {
                    $this->checkboxHelper->fiscaliseOrder((int)$order->id);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
}
