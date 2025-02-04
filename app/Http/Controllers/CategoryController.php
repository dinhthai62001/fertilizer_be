<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // Lấy danh sách danh mục
    public function index()
    {
        $categories = Category::all();
        return response()->json($categories);
    }

    // Tạo danh mục mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Category::create($validated);
        return response()->json($category, 201);
    }

    // Hiển thị thông tin danh mục theo id
    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }

    // Cập nhật thông tin danh mục
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $slug = Str::slug($validated['name']);
        // Cập nhật tất cả các sản phẩm liên quan
        Product::where('category_id', $category->id)->update(['category_slug' => $slug]);

        $category->update($validated);
        return response()->json($category);
    }

    // Xóa danh mục
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        // Xóa tất cả các sản phẩm liên quan đến category này
        $category->products()->delete();
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
