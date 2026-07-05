<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    protected ProductImportService $importService;

    /**
     * Create a new controller instance.
     */
    public function __construct(ProductImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Display a listing of the products.
     */
    #[OA\Get(
        path: '/api/products',
        summary: 'Get list of active products',
        description: 'Returns a paginated list of active products with their associated variants.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Page number for pagination',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response with products and pagination data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'iPhone 15'),
                                    new OA\Property(property: 'slug', type: 'string', example: 'iphone-15'),
                                    new OA\Property(property: 'description', type: 'string', example: 'The iPhone 15 features...'),
                                    new OA\Property(property: 'summary', type: 'string', example: 'Durable design, 48MP...'),
                                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                                    new OA\Property(property: 'thumbnail', type: 'string', example: 'http://...'),
                                    new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string')),
                                    new OA\Property(property: 'options', type: 'array', items: new OA\Items(type: 'object')),
                                    new OA\Property(
                                        property: 'variants',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                                new OA\Property(property: 'name', type: 'string', example: 'iPhone 15 - Black / 128GB'),
                                                new OA\Property(property: 'sku', type: 'string', example: 'IPH15-BLK-128'),
                                                new OA\Property(property: 'barcode', type: 'string', example: '195949033348'),
                                                new OA\Property(property: 'price', type: 'number', format: 'float', example: 799.00),
                                                new OA\Property(property: 'compare_at_price', type: 'number', format: 'float', nullable: true, example: 899.00),
                                                new OA\Property(property: 'inventory_quantity', type: 'integer', example: 50),
                                                new OA\Property(property: 'track_inventory', type: 'boolean', example: true),
                                                new OA\Property(property: 'continue_selling_out_of_stock', type: 'boolean', example: false),
                                                new OA\Property(property: 'weight', type: 'number', format: 'float', example: 171.00),
                                                new OA\Property(property: 'weight_unit', type: 'string', example: 'g'),
                                                new OA\Property(
                                                    property: 'dimensions',
                                                    type: 'object',
                                                    properties: [
                                                        new OA\Property(property: 'width', type: 'number', format: 'float', example: 7.16),
                                                        new OA\Property(property: 'height', type: 'number', format: 'float', example: 14.76),
                                                        new OA\Property(property: 'depth', type: 'number', format: 'float', example: 0.78),
                                                        new OA\Property(property: 'unit', type: 'string', example: 'cm'),
                                                    ]
                                                ),
                                                new OA\Property(property: 'options', type: 'object', example: ['Storage' => '128GB', 'Color' => 'Black']),
                                            ]
                                        )
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $products = Product::where('status', 'active')
            ->with('variants')
            ->paginate(12);

        return ProductResource::collection($products);
    }

    /**
     * Bulk import products from CSV files.
     */
    #[OA\Post(
        path: '/api/products/import',
        summary: 'Bulk import products from CSV files',
        description: 'Uploads multiple CSV files matching the naming pattern `YYYY_MM_DD_timestamp_productName_number.csv` and queues them for processing via RabbitMQ.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['files'],
                    properties: [
                        new OA\Property(
                            property: 'files[]',
                            description: 'List of CSV files to import',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary')
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Files accepted and queued for processing',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'CSV files have been successfully queued for importing.'),
                        new OA\Property(property: 'queued_files', type: 'array', items: new OA\Items(type: 'string', example: '2026_06_30_1782860289_product_1.csv')),
                        new OA\Property(property: 'invalid_files', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failure or all files failed naming format check'
            ),
        ]
    )]
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|mimes:csv,txt',
        ]);

        $queuedFiles = [];
        $invalidFiles = [];

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();

            try {
                $this->importService->importFile($file, $originalName);
                $queuedFiles[] = $originalName;
            } catch (\InvalidArgumentException $e) {
                $invalidFiles[] = [
                    'filename' => $originalName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (empty($queuedFiles)) {
            return response()->json([
                'message' => 'No files were successfully queued for import.',
                'invalid_files' => $invalidFiles,
            ], 422);
        }

        return response()->json([
            'message' => 'CSV files have been successfully queued for importing.',
            'queued_files' => $queuedFiles,
            'invalid_files' => $invalidFiles,
        ], 202);
    }
}
