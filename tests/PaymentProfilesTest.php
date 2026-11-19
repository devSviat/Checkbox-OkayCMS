<?php

namespace Modules\Sviat\Checkbox;

use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Carriers\CodCarriers;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentProfile;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentProfiles;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;

/**
 * Один спосіб оплати буває післяплатою кількох перевізників, а засіб оплати в чеку в кожного свій: НП — «Платіж
 * NovaPay», ROZETKA — «Платіж ROZETKAPAY». Рядок перевізника діє лише тоді, коли його післяплату позначено на
 * способі оплати і доставка замовлення — його; інакше чек іде за рядком способу оплати, як до 1.8.0.
 */
class PaymentProfilesTest extends TestCase
{
    private const array MODULE_IDS = ['Sviat/NovaPoshta' => 11, 'Sviat/Ukrposhta' => 12, 'Sviat/RozetkaDelivery' => 13, 'OkayCMS/Pickup' => 14];
    private const array DELIVERIES = ['np' => 21, 'up' => 22, 'rz' => 23, 'pickup' => 24];

    private ?string $rozetkaFiscal = 'shop';
    private int $lookups = 0;

    private static function method(array $over = []): object
    {
        return (object)($over + [
            'id' => 18,
            'module' => 'null',
            Init::PAYMENT_TYPE_FIELD => 'CASHLESS',
            Init::PAYMENT_LABEL_FIELD => 'Платіж NovaPay',
            Init::PAYMENT_SKIP_FIELD => 0,
        ]);
    }

    private static function order(string $delivery): object
    {
        return (object)['id' => 7, 'payment_method_id' => 18, 'delivery_id' => self::DELIVERIES[$delivery]];
    }

    private function profiles(?object $method = null): CheckboxPaymentProfiles
    {
        $moduleOfDelivery = [21 => 11, 22 => 12, 23 => 13, 24 => 14];
        $deliveries = $this->createStub(DeliveriesEntity::class);
        $deliveries->method('find')->willReturnCallback(function (array $filter) use ($moduleOfDelivery): array {
            $this->lookups++;

            return array_map(static fn(int $id): object => (object)['id' => $id, 'module_id' => $moduleOfDelivery[$id] ?? 0], $filter['id']);
        });
        $modules = $this->createStub(ModulesEntity::class);
        $modules->method('find')->willReturnCallback(function (array $filter): array {
            $this->lookups++;
            $rows = [];
            foreach ($filter['id'] as $id) {
                $name = array_search((int)$id, self::MODULE_IDS, true);
                if ($name !== false) {
                    [$vendor, $module] = explode('/', $name);
                    $rows[] = (object)['id' => $id, 'vendor' => $vendor, 'module_name' => $module];
                }
            }

            return $rows;
        });
        $payments = $this->createStub(PaymentsEntity::class);
        $payments->method('findOne')->willReturn($method ?? self::method());
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $class): object => match ($class) {
            DeliveriesEntity::class => $deliveries,
            ModulesEntity::class => $modules,
            PaymentsEntity::class => $payments,
        });
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(fn(string $name): ?string => $name === 'sviat__rozetka__cod_fiscal' ? $this->rozetkaFiscal : null);

        return new CheckboxPaymentProfiles($factory, new CodCarriers($factory, $settings));
    }

    private static function rows(array $rows): string
    {
        return json_encode($rows, JSON_UNESCAPED_UNICODE);
    }

    public function testNoCarrierCodUsesPaymentMethodRow(): void
    {
        $profile = $this->profiles()->forOrder(self::order('rz'), self::method());

        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay', false), $profile);
        self::assertSame(0, $this->lookups, 'без позначки післяплати доставка замовлення не потрібна');
    }

    /** Без збереженого рядка НП бере рядок способу оплати: чек той самий, що до 1.8.0. */
    public function testNovaPoshtaWithoutOverrideIsTheMethodRow(): void
    {
        $method = self::method(['sviat__novaposhta__cod' => 1, 'sviat__ukrposhta__cod' => 1]);

        self::assertEquals(CheckboxPaymentProfile::fromMethod($method), $this->profiles()->forOrder(self::order('np'), $method));
    }

    /** Укрпошта без збереженого рядка: безготівкова «Післяплата»; «не відправляти» — зі способу оплати. */
    public function testUkrposhtaDefaultsToPislyaplata(): void
    {
        $method = self::method(['sviat__ukrposhta__cod' => 1, Init::PAYMENT_TYPE_FIELD => 'CASH', Init::PAYMENT_SKIP_FIELD => 1]);

        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Післяплата', true), $this->profiles()->forOrder(self::order('up'), $method));
    }

    public function testRozetkaDefaultsToRozetkaPay(): void
    {
        $method = self::method(['sviat__novaposhta__cod' => 1, 'sviat__rozetka__cod' => 1]);
        $profiles = $this->profiles();

        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж ROZETKAPAY', false), $profiles->forOrder(self::order('rz'), $method));
        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay', false), $profiles->forOrder(self::order('np'), $method));
    }

    public function testSavedCarrierRowsWin(): void
    {
        $method = self::method([
            'sviat__novaposhta__cod' => 2,
            'sviat__rozetka__cod' => 1,
            Init::CARRIER_PAYMENTS_FIELD => self::rows([
                'novaposhta' => ['type' => 'CASHLESS', 'label' => 'Платіж NovaPay (контроль)', 'skip' => false],
                'rozetka' => ['type' => 'CASHLESS', 'label' => 'платіж RozetkaPay', 'skip' => true],
            ]),
        ]);
        $profiles = $this->profiles();

        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay (контроль)', false), $profiles->forOrder(self::order('np'), $method));
        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'платіж RozetkaPay', true), $profiles->forOrder(self::order('rz'), $method), '«не відправляти» діє для рядка перевізника');
        self::assertEquals(CheckboxPaymentProfile::fromMethod($method), $profiles->forOrder(self::order('pickup'), $method), 'самовивіз — рядок способу оплати');
    }

    /** Рядок діє лише для перевізника, чию післяплату позначено, і лише для його доставки. */
    public function testCarrierRowNeedsBothCodMarkAndItsDelivery(): void
    {
        $saved = [Init::CARRIER_PAYMENTS_FIELD => self::rows(['rozetka' => ['type' => 'CASH', 'label' => 'інший', 'skip' => true]])];

        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay', false), $this->profiles()->forOrder(self::order('rz'), self::method($saved)), 'позначку ROZETKA знято');
        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay', false), $this->profiles()->forOrder(self::order('np'), self::method($saved + ['sviat__rozetka__cod' => 1])), 'доставка не ROZETKA');
        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay', false), $this->profiles()->forOrder(self::order('rz'), self::method($saved + ['sviat__rozetka__cod' => 1, 'module' => 'LiqPay'])), 'онлайн-оплата не буває післяплатою');
        $npSaved = [Init::CARRIER_PAYMENTS_FIELD => self::rows(['novaposhta' => ['type' => 'CASH', 'label' => 'збережений рядок НП', 'skip' => true]]), 'sviat__rozetka__cod' => 1];
        self::assertEquals(new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay', false), $this->profiles()->forOrder(self::order('np'), self::method($npSaved)), 'післяплату НП не позначено, хоч рядок НП і збережено');
    }

    /**
     * Чек за післяплату ROZETKA видає магазин лише з `cod_fiscal = shop`; інакше рядок ROZETKA завжди «не
     * відправляти»: ручна позначка «оплачено» не має дати другий чек поверх чека ЕВО.
     */
    public function testRozetkaRowNeverSendsUnlessShopIssuesReceipts(): void
    {
        $method = self::method(['sviat__rozetka__cod' => 1, Init::CARRIER_PAYMENTS_FIELD => self::rows(['rozetka' => ['type' => 'CASHLESS', 'label' => 'Платіж ROZETKAPAY', 'skip' => false]])]);
        foreach (['unknown', 'provider', null, ''] as $fiscal) {
            $this->rozetkaFiscal = $fiscal;

            self::assertTrue($this->profiles()->forOrder(self::order('rz'), $method)?->skip, var_export($fiscal, true));
        }
        $this->rozetkaFiscal = 'shop';
        self::assertFalse($this->profiles()->forOrder(self::order('rz'), $method)?->skip);
        self::assertFalse($this->profiles()->forOrder(self::order('rz'), self::method(['sviat__rozetka__cod' => 1, Init::PAYMENT_SKIP_FIELD => 1]))?->skip, 'типовий рядок ROZETKA не бере «не відправляти» способу оплати');
    }

    /** Вимкнена післяплата в модулі ROZETKA не міняє чека посилок, створених до вимкнення. */
    public function testRozetkaRowDoesNotDependOnModuleSwitch(): void
    {
        self::assertSame('Платіж ROZETKAPAY', $this->profiles()->forOrder(self::order('rz'), self::method(['sviat__rozetka__cod' => 1]))?->label);
    }

    public function testFindsPaymentMethodWhenNotGiven(): void
    {
        $method = self::method(['sviat__rozetka__cod' => 1]);

        self::assertSame('Платіж ROZETKAPAY', $this->profiles($method)->forOrder(self::order('rz'))?->label);
        self::assertNull($this->profiles()->forOrder((object)['id' => 7, 'payment_method_id' => 0, 'delivery_id' => 23]));
    }

    public function testBrokenStoredRowsFallBackToDefaults(): void
    {
        foreach (['не json', '[1,2]', self::rows(['rozetka' => 'x']), self::rows(['rozetka' => ['type' => 'OTHER', 'label' => ['x'], 'skip' => 'так']])] as $stored) {
            $profile = $this->profiles()->forOrder(self::order('rz'), self::method(['sviat__rozetka__cod' => 1, Init::CARRIER_PAYMENTS_FIELD => $stored]));

            self::assertSame('CASHLESS', $profile?->type, $stored);
            self::assertSame('Платіж ROZETKAPAY', $profile?->label, $stored);
        }
    }

    /**
     * Записується лише рядок, відмінний від свого типового: інакше будь-яке збереження сторінки «заморозило» б
     * рядок НП на старих значеннях способу оплати. Рядок, що знову дорівнює типовому, з колонки зникає.
     */
    public function testEncodeStoresOnlyRowsThatDifferFromDefaults(): void
    {
        $base = new CheckboxPaymentProfile('CASHLESS', 'Платіж NovaPay', false);
        $stored = self::rows([
            'novaposhta' => ['type' => 'CASH', 'label' => '', 'skip' => false],
            'ukrposhta' => ['type' => 'CASH', 'label' => 'Готівка', 'skip' => true],
        ]);

        $json = CheckboxPaymentProfiles::encode($stored, $base, [
            'novaposhta' => $base,
            'rozetka' => new CheckboxPaymentProfile('CASHLESS', 'Платіж ROZETKAPAY', false),
            'meest' => new CheckboxPaymentProfile('CASH', null, true),
        ]);
        self::assertSame(['ukrposhta' => ['type' => 'CASH', 'label' => 'Готівка', 'skip' => true]], json_decode((string)$json, true), 'НП і ROZETKA дорівнюють типовим, Укрпошту не надсилали');

        self::assertSame(['rozetka' => ['type' => 'CASHLESS', 'label' => 'платіж RozetkaPay', 'skip' => false]], json_decode((string)CheckboxPaymentProfiles::encode(null, $base, ['rozetka' => new CheckboxPaymentProfile('CASHLESS', 'платіж RozetkaPay', false)]), true));
        self::assertNull(CheckboxPaymentProfiles::encode(self::rows(['novaposhta' => ['type' => 'CASH', 'label' => '', 'skip' => false]]), $base, ['novaposhta' => $base]));
    }

    /** Пачка замовлень: доставки й модулі — по одному запиту на всю пачку, хоч би скільки в ній замовлень. */
    public function testBatchCostsTwoQueries(): void
    {
        $method = self::method(['sviat__novaposhta__cod' => 1, 'sviat__rozetka__cod' => 1]);
        $orders = [];
        for ($i = 1; $i <= 50; $i++) {
            $orders[] = (object)['id' => $i, 'payment_method_id' => 18, 'delivery_id' => $i % 2 ? 21 : 23];
        }

        $profiles = $this->profiles()->forOrders($orders, [18 => $method]);

        self::assertSame(2, $this->lookups);
        self::assertSame('Платіж NovaPay', $profiles[1]->label);
        self::assertSame('Платіж ROZETKAPAY', $profiles[2]->label);
    }

    /** Сток без жодного перевізника: колонок післяплати немає, і доставку ніхто не питає. */
    public function testStockWithoutCarriers(): void
    {
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willThrowException(new \LogicException('без перевізників сутності не потрібні'));
        $profiles = new CheckboxPaymentProfiles($factory, new CodCarriers($factory, null));

        self::assertEquals(new CheckboxPaymentProfile('CASH', null, true), $profiles->forOrder(self::order('np'), self::method([Init::PAYMENT_TYPE_FIELD => 'CASH', Init::PAYMENT_LABEL_FIELD => '', Init::PAYMENT_SKIP_FIELD => 1])));
    }

    /** Рядок payments чека 1.7.0, дослівно: без збереженого рядка перевізника чек НП і самовивозу той самий. */
    private static function paymentsBefore(object $paymentMethod, int $total): array
    {
        $paymentType = $paymentMethod->{Init::PAYMENT_TYPE_FIELD} ?? 'CASH';
        $payments = [[
            'type' => is_string($paymentType) ? $paymentType : 'CASH',
            'value' => $total,
        ]];
        $paymentLabel = $paymentMethod->{Init::PAYMENT_LABEL_FIELD} ?? null;
        if (!empty($paymentLabel) && is_string($paymentLabel)) {
            $payments[0]['label'] = $paymentLabel;
        }

        return $payments;
    }

    public function testSalePaymentsUnchangedWithoutOverride(): void
    {
        $methods = [
            self::method(['sviat__novaposhta__cod' => 1]),
            self::method(['sviat__ukrposhta__cod' => 1, Init::PAYMENT_TYPE_FIELD => 'CASH', Init::PAYMENT_LABEL_FIELD => '']),
            self::method(['sviat__novaposhta__cod' => 1, Init::PAYMENT_LABEL_FIELD => '0']),
            self::method(['sviat__ukrposhta__cod' => 1, Init::PAYMENT_TYPE_FIELD => null, Init::PAYMENT_LABEL_FIELD => null]),
        ];
        foreach ($methods as $i => $method) {
            foreach (['np', 'pickup'] as $delivery) {
                $profile = $this->profiles()->forOrder(self::order($delivery), $method);

                self::assertSame(self::paymentsBefore($method, 125050), CheckboxReceiptsHelper::salePayments($profile, 125050), "{$i}/{$delivery}");
            }
        }
        self::assertSame([['type' => 'CASH', 'value' => 100]], CheckboxReceiptsHelper::salePayments(null, 100), 'без способу оплати — готівка, як до 1.8.0');
    }

    public function testSalePaymentsForRozetkaOrder(): void
    {
        $profile = $this->profiles()->forOrder(self::order('rz'), self::method(['sviat__novaposhta__cod' => 1, 'sviat__rozetka__cod' => 1]));

        self::assertSame([['type' => 'CASHLESS', 'value' => 99900, 'label' => 'Платіж ROZETKAPAY']], CheckboxReceiptsHelper::salePayments($profile, 99900));
    }
}

