<?php

namespace Modules\Sviat\Checkbox;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * На створенні товару в хук приходить порожній stdClass без id. Звертання до
 * нього друкує попередження в тіло відповіді, після чого Response уже не може
 * виставити жодного заголовка - сторінка створення товару сипала трьома
 * warning'ами поспіль.
 */
class ProductTaxGroupsHookTest extends TestCase
{
    private array $assigned = [];

    /** @var list<int> */
    private array $lookedUp = [];

    private function buildExtender(): BackendExtender
    {
        $this->assigned = [];
        $this->lookedUp = [];

        $design = $this->createStub(Design::class);
        $design->method('assign')->willReturnCallback(
            function ($name, $value) {
                $this->assigned[$name] = $value;
            }
        );

        $taxGroups = $this->createStub(TaxGroupsEntity::class);
        $taxGroups->method('find')->willReturn([]);
        $taxGroups->method('getProductTaxGroups')->willReturnCallback(
            function (int $productId) {
                $this->lookedUp[] = $productId;

                return [7];
            }
        );

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($taxGroups);

        $extender = (new ReflectionClass(BackendExtender::class))->newInstanceWithoutConstructor();
        foreach (['design' => $design, 'entityFactory' => $factory] as $property => $value) {
            (new \ReflectionProperty(BackendExtender::class, $property))->setValue($extender, $value);
        }

        return $extender;
    }

    public function testNewProductDoesNotTouchMissingId(): void
    {
        $extender = $this->buildExtender();

        $extender->getProduct(new \stdClass());

        $this->assertSame([], $this->assigned['checkboxProductTaxes']);
        $this->assertSame([], $this->lookedUp, 'на новому товарі запит до бази зайвий');
    }

    public function testExistingProductKeepsLookup(): void
    {
        $extender = $this->buildExtender();

        $product = new \stdClass();
        $product->id = 42;
        $extender->getProduct($product);

        $this->assertSame([7], $this->assigned['checkboxProductTaxes']);
        $this->assertSame([42], $this->lookedUp);
    }
}
