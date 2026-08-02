<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Seed YokPrinting's actual 108-item starting catalog.
     */
    public function run(): void
    {
        $categories = [];

        foreach ($this->catalogRows() as [$code, $name]) {
            $categoryName = $this->categoryFor($name);
            $category = $categories[$categoryName] ??= ProductCategory::query()->firstOrCreate(
                ['slug' => str($categoryName)->slug()->toString()],
                [
                    'name' => $categoryName,
                    'description' => "Katalog produk {$categoryName} YokPrinting.ID.",
                    'sort_order' => count($categories) + 10,
                    'status' => ProductCategory::STATUS_ACTIVE,
                ],
            );
            $existingMinimumStock = Product::query()
                ->where('sku', $code)
                ->value('minimum_stock');

            Product::query()->updateOrCreate(
                ['sku' => $code],
                [
                    'name' => $name,
                    'category_id' => $category->getKey(),
                    'category' => $category->name,
                    'brand' => $this->brandFor($name),
                    'cup_size' => $this->cupSizeFor($name),
                    'cup_model' => $this->cupModelFor($name),
                    'grammage' => $this->grammageFor($name),
                    'screen_printing_color' => null,
                    'sides' => null,
                    'description' => 'Satuan utama Pcs. Konversi operasional dicatat di master produk: 1 Dus = 1.000 Pcs. Kelipatan order invoice 500 Pcs.',
                    'short_description' => '1 Dus = 1.000 Pcs; kelipatan order 500 Pcs',
                    'unit' => Product::UNIT_PCS,
                    'purchase_price' => 0,
                    'stock' => 0,
                    'minimum_stock' => $existingMinimumStock ?? 500,
                    'minimum_order_qty' => 500,
                    'package_conversion' => 500,
                    'length_cm' => null,
                    'width_cm' => null,
                    'height_cm' => null,
                    'weight_gram' => null,
                    'dimensions' => [
                        'conversion_note' => '1 Dus = 1.000 Pcs',
                    ],
                    'moq_quantity' => 500,
                    'order_increment' => 500,
                    'packaging_unit' => 'Pcs',
                    'track_stock' => true,
                    'status' => Product::STATUS_ACTIVE,
                ],
            );
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function catalogRows(): array
    {
        return collect(explode("\n", trim(<<<'CATALOG'
H-001	Cup Injection 12Oz Datar (360ml) Natural
H-002	Cup Injection 12Oz Oval (380ml) Natural
H-003	Cup Injection 14Oz Datar Black Frosted
H-004	Cup Injection 14Oz Datar (400ml) Natural
H-005	Cup Injection 14Oz Datar Frosted
H-006	Cup Injection 16Oz Datar Frosted
H-007	Cup Injection 16Oz Datar (500ml) Natural
H-008	Cup Injection 22Oz Datar Frosted
H-009	Cup PET 12Oz Datar SJP
H-010	Cup PET 12Oz Oval PLP
H-011	Cup PET 14Oz Oval SJP
H-012	Cup PET 16Oz Datar SJP
H-013	Cup PET 8Oz Datar
H-014	Cup PP 12Oz Datar 7GR ASB
H-015	Cup PP 12Oz Datar 7GR MCup PM
H-016	Cup PP 12Oz Datar 7GR SJP
H-017	Cup PP 12Oz Oval 7GR SJP
H-018	Cup PP 14Oz Datar 7GR ASB
H-019	Cup PP 14Oz Datar 7GR MCup
H-020	Cup PP 14Oz Datar 7GR SJP
H-021	Cup PP 14Oz Oval 7GR ASB
H-022	Cup PP 14Oz Oval 7GR MCup
H-023	Cup PP 16Oz Datar 7GR SJP
H-024	Cup PP 16Oz Datar 8GR ASB
H-025	Cup PP 16Oz Oval 8GR SJP
H-026	Cup PP 22Oz Datar 9GR ASB
H-027	Cup PP 22Oz Datar 9GR MCup
H-028	Cup PP 22Oz Datar 9GR MCup PM
H-029	Cup PP 22Oz Datar 9GR SJP
H-030	Cup PP 22Oz Oval 9GR SJP
H-031	Cup PP 22oz Datar 9gr Policup
H-032	LID Bowl 650ml
H-033	LID Hot 8Oz Black
H-034	Paper Bowl 650ml
H-035	Paper Cup DW Kraft 8Oz
H-036	Tutup Dome PLP D92
H-037	Tutup Flat PLP D93
H-038	Tutup Flat SJP
H-039	Tutup Injection Sambung Hitam
H-040	Tutup Injection Sambung Natural
H-041	Tutup Injection Sambung Putih
H-042	Tutup Injection Sambung Frosted
H-043	Tutup Strawless Mcup 93
H-044	Tutup Strawless New PLP D93
H-045	Tutup Sippy Old PLP D92
H-046	Tutup Sippy Old PLP D93
H-047	Tutup Strawless SJP D92
H-048	Tutup Strawless SJP D93
H-049	Tutup Injection Sambung Kuning
H-050	Cup PET 8Oz Datar PLP
H-051	Tutup Flat PLP D92
H-052	Tutup Injection Sambung Biru
H-053	Paper Cup DW White 8Oz
H-054	Paper Cup DW Black 8Oz
H-055	Paper Cup Single Wall Black 8Oz
H-056	Paper Cup Single Wall White 8Oz
H-057	Paper Cup Single Wall White 12Oz
H-058	Paper Cup Single Wall White 16Oz
H-059	Paper Cup Single Wall White 22Oz
H-060	Paper Cup DW Kraft 12Oz
H-061	LID Hot 8Oz White
H-062	LID Hot 12Oz Black
H-063	LID Hot 12Oz White
H-064	LID Cold 8Oz Paper Cup
H-065	LID Cold 12Oz Paper Cup
H-066	LID Cold 16/22Oz Paper Cup
H-067	Paper Cup 4Oz
H-068	Cup Injection 12Oz Oval (380ml) Frosted
H-069	Cup Injection 12Oz Datar (360ml) Frosted
H-070	Cup Injection 16Oz Datar (500ml) Natural LEO
H-071	Cup Injection 16Oz Datar Black
H-072	Cup Injection 22Oz Datar Natural
H-073	Cup Injection 14Oz Datar Black Glossy
H-074	Cup Injection 16Oz Datar Black Glossy
H-075	Cup Injection 16Oz Datar Black Frosted
H-076	Cup PP 14Oz Oval 7GR SJP
H-077	Cup PP 16Oz Oval 8GR ASB
H-078	LID Flip Natural
H-079	Cup PET 14Oz Datar PLP
H-080	Cup PET 12Oz Datar PLP
H-081	Tutup Injection Flip Frosted
H-082	Tutup Injection Sambung Orange
H-083	LID Flip Red
H-084	Cup PET 8Oz Oval PLP
H-085	Cup PET 22Oz Datar PLP
H-086	Cup Injection 22Oz Datar Black Frosted
H-087	Cup PP 18Oz Datar 8GR SJP
H-088	Cup PP 18Oz Oval 8GR SJP
H-089	Cup PET 12Oz Oval SJP
H-090	LID Flip White
H-091	Cup PP 12Oz Datar 7GR Polycup
H-092	Tutup Injection Sambung Merah
H-093	Tutup Injection Sambung Hijau
H-094	LID Flip Black
H-095	Tutup Dome SJP D92
H-096	LID Sippy D93
H-097	Cup PP 16Oz Datar 7GR MCup
H-098	Cup PP 16Oz Oval 8GR MCup
H-099	Cup PP 18Oz Datar 8GR MCup
H-100	Cup PP 18Oz Oval 8GR MCup
H-101	Cup PP 22Oz Oval 9GR MCup
H-102	Cup PP 22Oz Oval 9GR Slim TipTop
H-103	Paper Bowl 800ml
H-104	LID Bowl 800ml
H-105	Sedotan Hitam 66ml
H-106	Paper Bowl 360ml
H-107	Paper Bowl 500ml
H-108	LID Bowl 360ml
CATALOG)))
            ->map(fn (string $line): array => explode("\t", $line, 2))
            ->all();
    }

    private function categoryFor(string $name): string
    {
        return match (true) {
            str_contains($name, 'Cup Injection') => 'Cup Injection',
            str_contains($name, 'Cup PET') => 'Cup PET',
            str_contains($name, 'Cup PP') => 'Cup PP',
            str_contains($name, 'Paper Cup') => 'Paper Cup',
            str_contains($name, 'Paper Bowl') => 'Paper Bowl',
            str_contains($name, 'Tutup'), str_contains($name, 'LID') => 'Tutup / Lid',
            str_contains($name, 'Sedotan') => 'Aksesoris',
            default => 'Produk Kemasan F&B',
        };
    }

    private function brandFor(string $name): ?string
    {
        foreach (['Slim TipTop', 'Polycup', 'Policup', 'MCup PM', 'MCup', 'Mcup', 'ASB', 'SJP', 'PLP', 'LEO'] as $brand) {
            if (str_contains($name, $brand)) {
                return match ($brand) {
                    'Mcup' => 'MCup',
                    default => $brand,
                };
            }
        }

        return null;
    }

    private function cupSizeFor(string $name): ?string
    {
        if (! preg_match('/(\d{1,2})(?:\/(\d{1,2}))?\s*Oz/i', $name, $matches)) {
            return null;
        }

        if (isset($matches[2]) && $matches[2] !== '') {
            return "{$matches[1]}/{$matches[2]} Oz";
        }

        return "{$matches[1]} Oz";
    }

    private function cupModelFor(string $name): ?string
    {
        return match (true) {
            str_contains($name, 'Datar') => 'Datar',
            str_contains($name, 'Oval') => 'Oval',
            default => null,
        };
    }

    private function grammageFor(string $name): ?string
    {
        if (! preg_match('/(\d+(?:\.\d+)?)\s*GR/i', $name, $matches)) {
            return null;
        }

        return "{$matches[1]}gr";
    }
}
