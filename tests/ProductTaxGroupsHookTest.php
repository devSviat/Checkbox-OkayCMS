<?php

namespace Modules\Sviat\Checkbox;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Closure;
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

    /** @var list<int> */
    private array $added = [];

    /** @var list<int> */
    private array $cleared = [];

    private function buildExtender(): BackendExtender
    {
        return $this->buildExtenderWithPost([]);
    }

    /** @param array<array-key, mixed> $post */
    private function buildExtenderWithPost(array $post): BackendExtender
    {
        $this->assigned = [];
        $this->lookedUp = [];
        $this->added = [];
        $this->cleared = [];

        // Справжній Request, а не заглушка: перевіряється саме те, як він
        // читає $_POST. Заглушка тут узгодилась би з будь-якою помилкою
        // виклику — а помилка була рівно в аргументі.
        $_POST = $post;
        $request = new Request();

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
        $taxGroups->method('deleteProductTaxGroups')->willReturnCallback(
            function (int $productId) {
                $this->cleared[] = $productId;
            }
        );
        $taxGroups->method('addProductTaxGroup')->willReturnCallback(
            function (int $productId, int $taxId) {
                $this->added[] = $taxId;
            }
        );

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($taxGroups);

        $extender = (new ReflectionClass(BackendExtender::class))->newInstanceWithoutConstructor();

        // Closure::call, а не ReflectionProperty: на PHP 8.0 запис у приватну
        // властивість вимагає setAccessible(), а з 8.1 той самий виклик
        // задепрекейчено. Модуль їде на обох рушіях, тож потрібен спосіб без
        // розгалуження за версією.
        Closure::bind(
            function () use ($design, $factory, $request) {
                $this->design = $design;
                $this->entityFactory = $factory;
                $this->request = $request;
            },
            $extender,
            BackendExtender::class
        )();

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

    /**
     * Прив'язки товару до груп ПДВ спершу видаляються, потім записуються
     * наново. Якщо новий список не доїхав, збереження товару лишає товар без
     * жодної ставки — мовчки, і побачити це можна аж у чеку.
     */
    public function testSavedTaxGroupsSurviveTheRewrite(): void
    {
        $extender = $this->buildExtenderWithPost(['checkboxTaxes' => ['5', '9']]);

        $product = new \stdClass();
        $product->id = 42;
        $extender->postProduct($product);

        $this->assertSame([42], $this->cleared);
        $this->assertSame([5, 9], $this->added);
    }

    /** Знята остання галочка — порожній список, а не «нічого не міняти». */
    public function testEmptySelectionClearsTheGroups(): void
    {
        $extender = $this->buildExtenderWithPost([]);

        $product = new \stdClass();
        $product->id = 42;
        $extender->postProduct($product);

        $this->assertSame([42], $this->cleared);
        $this->assertSame([], $this->added);
    }
}
