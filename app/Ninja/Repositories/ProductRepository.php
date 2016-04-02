<?php namespace App\Ninja\Repositories;

use DB;
use App\Ninja\Repositories\BaseRepository;

class ProductRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Product';
    }

    public function find($accountId)
    {
        return DB::table('products')
                ->leftJoin('tax_rates', function($join) {
                    $join->on('tax_rates.id', '=', 'products.default_tax_rate_id')
                         ->whereNull('tax_rates.deleted_at');
                })
                ->where('products.account_id', '=', $accountId)
                ->where('products.deleted_at', '=', null)
                ->select(
                    'products.public_id',
                    'products.product_key',
                    'products.notes',
                    'products.cost',
                    'tax_rates.name as tax_name',
                    'tax_rates.rate as tax_rate',
                    'products.deleted_at'
                );
    }
}