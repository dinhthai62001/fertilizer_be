<?php

namespace App\Services;

use App\Models\Product;

class ProductService
{
    /**
     * Lọc danh sách sản phẩm.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function filterProducts(array $filters)
    {
        // Tạo query cơ bản
        $query = Product::query();

        // Áp dụng filter category_id (nếu có)
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Thêm các filter khác nếu cần (ví dụ filter theo name hoặc price range)
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['price_min']) && isset($filters['price_max'])) {
            $query->whereBetween('price', [$filters['price_min'], $filters['price_max']]);
        }

        // Trả về danh sách sản phẩm sau khi filter
        return $query->get();
    }
}
