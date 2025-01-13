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


    // public function index(Request $request)
    // {
    //     // Trả danh sách sản phẩm
    //     $products = Product::all();

    //     $categoryId = $request->input('category_id');
    //     if ($categoryId) {
    //         $products = Product::where('category_id', $categoryId)->get();
    //     } else {
    //         $products = Product::all();
    //     }
    //     // Thêm URL đầy đủ cho ảnh khi trả về API
    //     foreach ($products as $product) {
    //         if ($product->image) {
    //             $product->image = asset($product->image); // Tạo URL đầy đủ từ đường dẫn
    //         }
    //     }

    //     return response()->json($products);
    // }
    // public function index($slug)
    // {
    //     // $categoryId = $request->input('category_id');
    //     // $products = $categoryId
    //     //     ? Product::where('category_id', $categoryId)->get()
    //     //     : Product::all();
    //     $category = Category::where('slug', $slug)->firstOrFail();

    //     // Lấy sản phẩm thuộc danh mục
    //     $products = Product::where('category_slug', $category->slug)->get();

    //     // Thêm URL đầy đủ cho các ảnh khi trả về API
    //     foreach ($products as $product) {
    //         if ($product->images) {
    //             // Nếu có ảnh, chuyển đổi mảng các đường dẫn ảnh thành URL đầy đủ
    //             $product->images = array_map(function ($image) {
    //                 return asset($image); // Tạo URL đầy đủ cho ảnh
    //             }, $product->images);
    //         }
    //     }

    //     return response()->json($products);
    // }

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

        // Lấy danh sách sản phẩm thuộc danh mục
        $products = Product::where('category_slug', $slug)
            // ->take(10) // Lấy tối đa 10 sản phẩm
            ->get()
            ->map(function ($product) {
                if ($product->image) {
                    $product->image = json_decode($product->image);
                    $product->image = array_map(fn($img) => asset($img), $product->image);
                }
                return $product;
            });

        return response()->json([
            'category_name' => $category->name,
            'products' => $products
        ]);
    }




    // public function store(Request $request)
    // {
    //     $validated  = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'price' => 'required|numeric',
    //         'images' => 'nullable|array', 
    //         'images.*' => 'image|max:2048', // Mỗi ảnh phải là file hình ảnh
    //         'category_id' => 'exists:categories,id',
    //     ]);

    //     if ($request->category_id) {
    //         $category = Category::where('id', $request->category_id)->firstOrFail();

    //         $validated['category_slug'] = $category->slug;
    //     }

    //     $imagePaths = [];

    //     if ($request->hasFile('images')) {
    //         foreach ($request->file('images') as $file) {

    //             $filename = time() . '_' . $file->getClientOriginalName();
    //             // Lưu file vào thư mục public/uploads/products
    //             $file->move(public_path('uploads/products'), $filename);
    //             // Lưu đường dẫn file vào mảng
    //             $imagePaths[] = asset('uploads/products/' . $filename);
    //         }
    //     }

    //     // Thêm mảng ảnh vào dữ liệu và lưu dưới dạng JSON
    //     $validated['image'] = json_encode($imagePaths);
    //     dump($validated);
    //     // Tạo sản phẩm mới
    //     $product = Product::create($validated);

    //     return response()->json([
    //         'product' => $product,
    //         'message' => 'Sản phẩm được tạo thành công',
    //         'images' => $imagePaths, // Trả về danh sách ảnh đã upload
    //     ], 201);
    // }

    public function store(Request $request)
    {
        // Validate dữ liệu đầu vào
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'contents' => 'nullable|string',
            'images' => 'nullable|array', // Chấp nhận mảng ảnh
            'images.*' => 'image|max:2048', // Mỗi ảnh phải là file hình ảnh
            'category_id' => 'nullable|exists:categories,id',
        ]);
        if ($request->category_id) {
            $category = Category::where('id', $request->category_id)->firstOrFail();

            $validated['category_slug'] = $category->slug;
        }


        $imagePaths = []; // Mảng chứa các đường dẫn ảnh đã upload

        // Kiểm tra và lưu các ảnh nếu có
        if ($request->hasFile('images')) { // Kiểm tra nếu có file trong mảng images
            foreach ($request->file('images') as $file) {
                // Tạo tên file duy nhất
                $filename = time() . '_' . $file->getClientOriginalName();
                // Lưu file vào thư mục public/uploads/products
                $file->move(public_path('uploads/products'), $filename);
                // Lưu đường dẫn file vào mảng
                $imagePaths[] = asset('uploads/products/' . $filename);
            }
        }

        // Thêm mảng ảnh vào dữ liệu và lưu dưới dạng JSON
        $validated['image'] = json_encode($imagePaths);

        // Tạo sản phẩm mới
        $product = Product::create($validated);

        return response()->json([
            'product' => $product,
            'message' => 'Sản phẩm được tạo thành công',
            'images' => $imagePaths, // Trả về danh sách ảnh đã upload
        ], 201);
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)->firstOrFail();
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product);
    }


    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'image' => 'nullable|max:2048',
        ]);
        return response()->json([
            'request_data' => $request->all(), // Trả về toàn bộ request để kiểm tra
            // 'has_image' => $request->image
        ]);
        //  dd($request->hasFile('image'))
        // if ($request->hasFile('image')) {
        //     if ($product->image && file_exists(public_path($product->image))) {
        //         unlink(public_path($product->image));
        //     }

        //     $file = $request->file('image');
        //     $filename = time() . '_' . $file->getClientOriginalName();
        //     $file->move(public_path('uploads/products'), $filename);

        //     $validated['image'] = 'uploads/products/' . $filename;
        // }

        // $product->update($validated);
        // $product->refresh();

        // return response()->json($product);
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
