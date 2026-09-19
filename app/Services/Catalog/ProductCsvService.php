<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductCsvService
{
    public const HEADERS = [
        'sku',
        'name_en',
        'name_fa',
        'name_ps',
        'category_id',
        'brand_id',
        'base_unit_code',
        'purchase_cost',
        'selling_price',
        'minimum_selling_price',
        'wholesale_price',
        'minimum_stock',
        'reorder_quantity',
        'track_stock',
        'track_expiry',
        'primary_barcode',
        'shelf_location',
    ];

    public function __construct(
        private readonly ProductService $products,
        private readonly AuditLogger $audit,
    ) {
    }

    public function writeTemplate($stream): void
    {
        fputcsv($stream, self::HEADERS);
    }

    public function writeExport($stream): void
    {
        fputcsv($stream, self::HEADERS);

        Product::query()
            ->with(['baseUnit', 'barcodes'])
            ->orderBy('id')
            ->chunkById(250, function ($products) use ($stream): void {
                foreach ($products as $product) {
                    $primary = $product->barcodes->firstWhere('is_primary', true) ?? $product->barcodes->first();

                    fputcsv($stream, [
                        $product->sku,
                        $product->name_en,
                        $product->name_fa,
                        $product->name_ps,
                        $product->category_id,
                        $product->brand_id,
                        $product->baseUnit?->code,
                        $product->purchase_cost,
                        $product->selling_price,
                        $product->minimum_selling_price,
                        $product->wholesale_price,
                        $product->minimum_stock,
                        $product->reorder_quantity,
                        $product->track_stock ? '1' : '0',
                        $product->track_expiry ? '1' : '0',
                        $primary?->barcode,
                        $product->shelf_location,
                    ]);
                }
            });
    }

    public function import(string $path, User $actor): int
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the uploaded CSV file.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw ValidationException::withMessages(['file' => __('ui.import_csv_empty')]);
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));

            if (array_values($header) !== self::HEADERS) {
                throw ValidationException::withMessages(['file' => __('ui.import_csv_headers_invalid')]);
            }

            $rows = [];
            $errors = [];
            $seenSkus = [];
            $seenBarcodes = [];
            $line = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->isBlankRow($values)) {
                    continue;
                }

                if (count($values) !== count(self::HEADERS)) {
                    $errors["file.$line"] = __('ui.import_csv_column_count', ['line' => $line]);
                    continue;
                }

                if (count($rows) >= 1000) {
                    $errors['file'] = __('ui.import_csv_too_many_rows');
                    break;
                }

                $row = array_combine(self::HEADERS, array_map(fn ($value) => trim((string) $value), $values));
                $row['track_stock'] = $this->parseBoolean($row['track_stock'], true);
                $row['track_expiry'] = $this->parseBoolean($row['track_expiry'], false);
                $row['category_id'] = $row['category_id'] === '' ? null : $row['category_id'];
                $row['brand_id'] = $row['brand_id'] === '' ? null : $row['brand_id'];

                if ($row['track_stock'] === null) {
                    $errors["file.$line.track_stock"] = __('ui.import_csv_boolean_invalid', ['line' => $line, 'field' => 'track_stock']);
                }

                if ($row['track_expiry'] === null) {
                    $errors["file.$line.track_expiry"] = __('ui.import_csv_boolean_invalid', ['line' => $line, 'field' => 'track_expiry']);
                }

                $validator = Validator::make($row, [
                    'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')],
                    'name_en' => ['required', 'string', 'max:200'],
                    'name_fa' => ['nullable', 'string', 'max:200'],
                    'name_ps' => ['nullable', 'string', 'max:200'],
                    'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
                    'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('is_active', true)],
                    'base_unit_code' => ['required', 'string', 'max:30'],
                    'purchase_cost' => ['nullable', 'decimal:0,2', 'min:0'],
                    'selling_price' => ['required', 'decimal:0,2', 'min:0'],
                    'minimum_selling_price' => ['nullable', 'decimal:0,2', 'min:0'],
                    'wholesale_price' => ['nullable', 'decimal:0,2', 'min:0'],
                    'minimum_stock' => ['nullable', 'decimal:0,6', 'min:0'],
                    'reorder_quantity' => ['nullable', 'decimal:0,6', 'min:0'],
                    'track_stock' => ['required', 'boolean'],
                    'track_expiry' => ['required', 'boolean'],
                    'primary_barcode' => ['nullable', 'string', 'max:191', Rule::unique('product_barcodes', 'barcode')],
                    'shelf_location' => ['nullable', 'string', 'max:100'],
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors["file.$line.".count($errors)] = __('ui.import_csv_row_error', ['line' => $line, 'message' => $message]);
                    }
                    continue;
                }

                $skuKey = mb_strtolower($row['sku']);
                if (isset($seenSkus[$skuKey])) {
                    $errors["file.$line.sku"] = __('ui.import_csv_duplicate_sku', ['line' => $line, 'sku' => $row['sku']]);
                }
                $seenSkus[$skuKey] = true;

                if ($row['primary_barcode'] !== '') {
                    if (isset($seenBarcodes[$row['primary_barcode']])) {
                        $errors["file.$line.primary_barcode"] = __('ui.import_csv_duplicate_barcode', ['line' => $line, 'barcode' => $row['primary_barcode']]);
                    }
                    $seenBarcodes[$row['primary_barcode']] = true;
                }

                $unit = Unit::query()
                    ->where('code', $row['base_unit_code'])
                    ->where('is_active', true)
                    ->first();

                if (! $unit) {
                    $errors["file.$line.base_unit_code"] = __('ui.import_csv_unit_invalid', ['line' => $line, 'code' => $row['base_unit_code']]);
                    continue;
                }

                if ($row['track_expiry'] && ! $row['track_stock']) {
                    $errors["file.$line.track_expiry"] = __('ui.expiry_requires_stock_tracking');
                    continue;
                }

                $row['base_unit_id'] = $unit->id;
                $rows[] = $row;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['file' => __('ui.import_csv_no_rows')]);
            }

            return DB::transaction(function () use ($rows, $actor): int {
                foreach ($rows as $row) {
                    $barcodes = [];

                    if ($row['primary_barcode'] !== '') {
                        $barcodes[] = [
                            'barcode' => $row['primary_barcode'],
                            'unit_id' => $row['base_unit_id'],
                            'is_primary' => true,
                        ];
                    }

                    $this->products->create([
                        'sku' => $row['sku'],
                        'name_en' => $row['name_en'],
                        'name_fa' => $row['name_fa'] ?: null,
                        'name_ps' => $row['name_ps'] ?: null,
                        'category_id' => $row['category_id'],
                        'brand_id' => $row['brand_id'],
                        'base_unit_id' => $row['base_unit_id'],
                        'purchase_cost' => $row['purchase_cost'] !== '' ? $row['purchase_cost'] : '0',
                        'selling_price' => $row['selling_price'],
                        'minimum_selling_price' => $row['minimum_selling_price'] ?: null,
                        'wholesale_price' => $row['wholesale_price'] ?: null,
                        'minimum_stock' => $row['minimum_stock'] !== '' ? $row['minimum_stock'] : '0',
                        'reorder_quantity' => $row['reorder_quantity'] !== '' ? $row['reorder_quantity'] : '0',
                        'track_stock' => $row['track_stock'],
                        'track_expiry' => $row['track_expiry'],
                        'shelf_location' => $row['shelf_location'] ?: null,
                        'barcodes' => $barcodes,
                    ], $actor);
                }

                $this->audit->record(
                    'inventory.products.imported',
                    newValues: ['count' => count($rows)],
                    actor: $actor,
                );

                return count($rows);
            });
        } finally {
            fclose($handle);
        }
    }

    private function isBlankRow(array $values): bool
    {
        return collect($values)->every(fn ($value) => trim((string) $value) === '');
    }

    private function parseBoolean(string $value, bool $default): ?bool
    {
        if ($value === '') {
            return $default;
        }

        return match (mb_strtolower($value)) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default => null,
        };
    }
}
