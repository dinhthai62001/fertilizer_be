<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Danh sách sản phẩm
    protected $productService;

    // Inject ProductService qua constructor
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index()
    {
        // Lấy tất cả sản phẩm và nhóm chúng theo category_slug
        $productsByCategory = Product::all()->groupBy('category_slug');

        // Tạo mảng kết quả để trả về
        $result = [];
        foreach ($productsByCategory as $categorySlug => $products) {
            $result[] = [
                'category_name' => $products->first()->category->name,
                'category_slug' => $categorySlug,
                'products' => $products->map(function ($product) {
                    // Đảm bảo đường dẫn đầy đủ cho ảnh
                    if ($product->image) {
                        $product->image = json_decode($product->image); // Giải mã JSON nếu lưu ảnh dưới dạng mảng
                        $product->image = array_map(fn($img) => asset($img), $product->image); // Thêm URL đầy đủ
                    }
                    return $product;
                }),
            ];
        }

        return response()->json($result);
    }


    public function getProduct($slug)
    {
        $category = Category::where('slug', $slug)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Lấy danh sách sản phẩm thuộc danh mục với phân trang
        $products = Product::where('category_slug', $slug)
            ->paginate(10); // Paginate results to 10 items per page

        // Map the products to format the image URLs
        $products->getCollection()->transform(function ($product) {
            if ($product->image) {
                $product->image = json_decode($product->image);
                $product->image = array_map(fn($img) => asset($img), $product->image);
            }
            return $product;
        });

        return response()->json([
            'category_name' => $category->name,
            'products' => $products->items(), // Only the items on the current page
            'pagination' => [
                'current_page' => $products->currentPage(),
                'total_pages' => $products->lastPage(),
                'total_items' => $products->total(),
                'per_page' => $products->perPage(),
                'next_page' => $products->hasMorePages() ? $products->nextPageUrl() : null,
                'previous_page' => $products->previousPageUrl() ? $products->previousPageUrl() : null
            ]
        ]);
    }

    public function store(Request $request)
    {

        // Validate dữ liệu đầu vào
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'contents' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'category_id' => 'nullable|exists:categories,id',
        ]);
        if (Product::where('name', $request->name)->exists()) {
            return response()->json([
                'message' => 'Tên sản phẩm đã tồn tại. Vui lòng chọn tên khác.',
            ], 422);
        }
        if ($request->category_id) {
            $category = Category::where('id', $request->category_id)->firstOrFail();

            $validated['category_slug'] = $category->slug;
        }
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/products'), $filename);
                // Lưu đường dẫn file vào mảng
                $imagePaths[] = asset('uploads/products/' . $filename);
            }
        }

        $validated['image'] = json_encode($imagePaths);

        $product = Product::create($validated);

        return response()->json([
            'product' => $product,
            'message' => 'Sản phẩm được tạo thành công',
            'images' => $request->name,
        ], 201);
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)->firstOrFail();
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        if ($product->image) {
            $product->image = json_decode($product->image); // Giải mã JSON nếu lưu ảnh dưới dạng mảng
            $product->image = array_map(fn($img) => asset($img), $product->image); // Thêm URL đầy đủ
        }
        return response()->json($product);
    }


    public function update(Request $request, $id)
    {
        // Validate dữ liệu đầu vào
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'contents' => 'nullable|string',
            'images' => 'nullable|array', // Chấp nhận mảng ảnh
            'images.*' => 'image|max:2048', // Mỗi ảnh phải là file hình ảnh
            'category_id' => 'nullable|exists:categories,id',
        ]);
        if (Product::where('name', $request->name)->exists()) {
            return response()->json([
                "message" => "Tên sản phẩm đã tồn tại. Vui lòng chọn tên khác."
            ], 422);
        }

        // Tìm sản phẩm cần cập nhật
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Sản phẩm không tồn tại'], 404);
        }

        // Nếu có category_id, lấy slug của danh mục
        if ($request->category_id) {
            $category = Category::where('id', $request->category_id)->firstOrFail();
            $validated['category_slug'] = $category->slug;
        }

        // Xóa ảnh cũ nếu có ảnh mới được upload
        $imagePaths = [];
        if ($request->hasFile('images')) {
            // Xóa các ảnh cũ
            if ($product->image) {
                $oldImages = json_decode($product->image, true);
                foreach ($oldImages as $oldImagePath) {
                    $filePath = public_path(str_replace(asset(''), '', $oldImagePath));
                    if (file_exists($filePath)) {
                        unlink($filePath); // Xóa file ảnh cũ
                    }
                }
            }

            // Lưu ảnh mới
            foreach ($request->file('images') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/products'), $filename);
                $imagePaths[] = asset('uploads/products/' . $filename); // Lưu đường dẫn file mới
            }

            // Gắn mảng ảnh mới vào dữ liệu validated
            $validated['image'] = json_encode($imagePaths);
        } else {
            // Nếu không có ảnh mới, giữ nguyên ảnh cũ
            $validated['image'] = $product->image;
        }

        // Cập nhật các thông tin sản phẩm
        $product->update($validated);

        return response()->json([
            'product' => $product,
            'message' => 'Sản phẩm được cập nhật thành công',
            'images' => $request->name, // Trả về danh sách ảnh mới (nếu có)
        ]);
    }

    // Xóa sản phẩm
    public function destroy($id)
    {
        $product = Product::find($id);

        // Kiểm tra nếu sản phẩm không tồn tại
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Lấy các đường dẫn ảnh từ trường `image` trong cơ sở dữ liệu
        if ($product->image) {
            $images = json_decode($product->image, true); // Giải mã JSON thành mảng

            // Xóa từng file ảnh khỏi thư mục lưu trữ
            foreach ($images as $imagePath) {
                $filePath = public_path(str_replace(asset(''), '', $imagePath)); // Loại bỏ base URL khỏi đường dẫn

                if (file_exists($filePath)) {
                    unlink($filePath); // Xóa file
                }
            }
        }

        // Xóa sản phẩm khỏi cơ sở dữ liệu
        $product->delete();

        // Trả về thông báo thành công
        return response()->json(['message' => 'Product and its images deleted successfully']);
    }
}
