<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Smoke test: every GET page an owner can open must actually render.
 *
 * This exists because /supplier-prices/create shipped permanently broken - a
 * ParseError 500 on every single request - and nothing caught it. Its Blade
 * template compiled fine (so `view:cache` reported success) but Blade's
 * directive-argument parser had truncated an inline `@json([...])` containing
 * a nested `request()->query('x', '')`, emitting unbalanced PHP that only blew
 * up at render time. Feature tests covered that module's API thoroughly and
 * its pages not at all.
 *
 * Rendering each page with an empty database is a deliberately low bar - it
 * catches broken templates, missing view variables and bad route() names,
 * which is exactly the class of bug that got through.
 */
class PageRoutesRenderTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    /**
     * Routes needing a model/parameter, and non-page endpoints. Those have
     * their own dedicated tests; this smoke test only covers parameterless
     * pages an owner can open straight from the navigation.
     */
    private const SKIPPED_PREFIXES = ['api.', 'login', 'logout', 'password.', 'verification.'];

    public function test_every_parameterless_owner_page_renders(): void
    {
        $failures = [];
        $checked = 0;

        /** @var Route $route */
        foreach (RouteFacade::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if ($route->parameterNames() !== []) {
                continue;
            }

            foreach (self::SKIPPED_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    continue 2;
                }
            }

            $checked++;
            $response = $this->get(route($name));

            // 200 renders, 302 redirects (e.g. "/" -> dashboard) are both fine;
            // a 500 is not.
            if ($response->getStatusCode() >= 500) {
                $failures[] = $name.' -> '.$response->getStatusCode();
            }
        }

        $this->assertGreaterThan(15, $checked, 'Expected to smoke-test the app pages, but found almost none.');
        $this->assertSame([], $failures, 'These pages failed to render: '.implode(', ', $failures));
    }

    public function test_supplier_price_pages_render(): void
    {
        // The two that were actually broken - named explicitly so a
        // regression names itself instead of hiding in the loop above.
        $this->get(route('supplier-prices.index'))->assertOk();
        $this->get(route('supplier-prices.create'))->assertOk();
    }

    public function test_supplier_price_create_page_keeps_its_prefilled_query_parameters(): void
    {
        // The truncated inline @json() dropped exactly these two values, so
        // arriving from a product's "add supplier price" link lost its
        // context even before the page started erroring outright.
        // The x-data attribute is single-quoted, so @json() leaves the double
        // quotes as-is here.
        $this->get(route('supplier-prices.create', ['supplier_id' => 7, 'product_id' => 9]))
            ->assertOk()
            ->assertSee('supplierPriceFormPage', false)
            ->assertSee('"supplierId":"7"', false)
            ->assertSee('"productId":"9"', false);
    }
}
