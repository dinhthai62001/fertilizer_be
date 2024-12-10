<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = ['name', 'slug', 'price', 'image', 'category_slug', 'category_id', 'contents'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    // Xử lý tự động thêm slug khi tạo hoặc cập nhật sản phẩm
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $product->slug = static::generateUniqueSlug($product->name);
        });

        static::updating(function ($product) {
            if ($product->isDirty('name')) { // Chỉ tạo slug mới nếu 'name' thay đổi
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });
    }

    public function scopeFilterByName($query, $name)
    {
        return $query->where('name', 'LIKE', '%' . $name . '%');
    }

    protected function mapFilters(): array
    {
        return [
            // Lọc theo slug
            'slug' => function ($value) {
                return function ($query) use ($value) {
                    $query->where('slug', 'like', "%$value%");
                };
            },
        ];
    }

    // Tạo slug duy nhất
    public static function generateUniqueSlug($name)
    {
        // Tạo slug từ name nhưng thay '-' bằng dấu cách
        $slug = Str::slug($name, '-');

        // Kiểm tra nếu đã tồn tại slug trùng lặp
        $count = Product::where('slug', 'like', $slug . '%')->count();

        // Nếu tồn tại slug trùng, thêm số vào cuối slug
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        return $slug;
    }
}
