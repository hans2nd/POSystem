<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoodsReceiptController extends Controller
{
    /**
     * Tampilkan halaman Daftar Barang Masuk (Item Receipt Log)
     */
    public function index(Request $request)
    {
        // Opsi A: Pastikan seluruh item diterima sudah tersinkron ke Inventaris Aset
        Asset::syncAllUnregisteredReceivedPoItems();

        $search = $request->get('search');
        $branchId = $request->get('branch_id');
        $asset = $request->get('asset');
        $receiptType = $request->get('receipt_type', 'all'); // all, full, partial
        $defaultDateFrom = now()->startOfMonth()->format('Y-m-d');
        $defaultDateTo = now()->format('Y-m-d');

        $dateFrom = $request->has('date_from') ? $request->get('date_from') : $defaultDateFrom;
        $dateTo = $request->has('date_to') ? $request->get('date_to') : $defaultDateTo;

        $query = PurchaseOrderDetail::with([
            'product.category',
            'purchaseOrder.vendor',
            'purchaseOrder.branch',
            'purchaseOrder.purchaseRequest',
            'assets',
        ])
        ->where('received_quantity', '>', 0)
        ->whereHas('purchaseOrder', function ($q) {
            $q->whereIn('status', [PurchaseOrder::STATUS_APPROVED]);
        });

        // Filter search (Nama barang, SKU, No PO, No PR, Vendor, Kategori, Tipe Asset)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                       ->orWhere('sku', 'like', "%{$search}%")
                       ->orWhereHas('category', function ($cq) use ($search) {
                           $cq->where('name', 'like', "%{$search}%")
                              ->orWhere('asset', 'like', "%{$search}%");
                       });
                })
                ->orWhereHas('purchaseOrder', function ($poq) use ($search) {
                    $poq->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', function ($vq) use ($search) {
                            $vq->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('purchaseRequest', function ($prq) use ($search) {
                            $prq->where('pr_number', 'like', "%{$search}%");
                        });
                });
            });
        }

        // Filter cabang
        if ($branchId) {
            $query->whereHas('purchaseOrder', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        // Filter tipe asset (HRGA, MIS, dll.)
        if ($asset) {
            $query->whereHas('product.category', function ($cq) use ($asset) {
                $cq->where('asset', $asset);
            });
        }

        // Filter status penerimaan item (lengkap vs parsial)
        if ($receiptType === 'full') {
            $query->whereColumn('received_quantity', '>=', 'quantity');
        } elseif ($receiptType === 'partial') {
            $query->whereColumn('received_quantity', '<', 'quantity');
        }

        // Filter rentang tanggal penerimaan
        if ($dateFrom) {
            $query->where(function ($q) use ($dateFrom) {
                $q->whereDate('received_at', '>=', $dateFrom)
                  ->orWhere(function ($subQ) use ($dateFrom) {
                      $subQ->whereNull('received_at')
                           ->whereHas('purchaseOrder', function ($poq) use ($dateFrom) {
                               $poq->whereDate('received_at', '>=', $dateFrom);
                           });
                  });
            });
        }

        if ($dateTo) {
            $query->where(function ($q) use ($dateTo) {
                $q->whereDate('received_at', '<=', $dateTo)
                  ->orWhere(function ($subQ) use ($dateTo) {
                      $subQ->whereNull('received_at')
                           ->whereHas('purchaseOrder', function ($poq) use ($dateTo) {
                               $poq->whereDate('received_at', '<=', $dateTo);
                           });
                  });
            });
        }

        // Sort by received_at desc, fallback to updated_at desc
        $items = $query->orderByRaw('COALESCE(received_at, updated_at) DESC')
                       ->paginate(15)
                       ->withQueryString();

        // Minimalist Stats
        $baseQuery = PurchaseOrderDetail::where('received_quantity', '>', 0)
            ->whereHas('purchaseOrder', function ($q) {
                $q->whereIn('status', [PurchaseOrder::STATUS_APPROVED]);
            });

        $stats = [
            'total_received_qty' => (clone $baseQuery)->sum('received_quantity'),
            'total_items_count'  => (clone $baseQuery)->count(),
            'full_received_count' => (clone $baseQuery)->whereColumn('received_quantity', '>=', 'quantity')->count(),
            'partial_received_count' => (clone $baseQuery)->whereColumn('received_quantity', '<', 'quantity')->count(),
        ];

        $branches = Branch::active()->orderBy('name')->get();
        $assetTypes = AssetType::active()->orderBy('name')->get();

        return view('goods-receipts.index', compact(
            'items',
            'stats',
            'branches',
            'assetTypes',
            'search',
            'branchId',
            'asset',
            'receiptType',
            'dateFrom',
            'dateTo',
            'defaultDateFrom',
            'defaultDateTo'
        ));
    }

    /**
     * Export daftar barang masuk ke format CSV ringkas
     */
    public function export(Request $request): StreamedResponse
    {
        $search = $request->get('search');
        $branchId = $request->get('branch_id');
        $asset = $request->get('asset');
        $receiptType = $request->get('receipt_type', 'all');
        $defaultDateFrom = now()->startOfMonth()->format('Y-m-d');
        $defaultDateTo = now()->format('Y-m-d');

        $dateFrom = $request->has('date_from') ? $request->get('date_from') : $defaultDateFrom;
        $dateTo = $request->has('date_to') ? $request->get('date_to') : $defaultDateTo;

        $query = PurchaseOrderDetail::with([
            'product.category',
            'purchaseOrder.vendor',
            'purchaseOrder.branch',
            'purchaseOrder.purchaseRequest',
        ])
        ->where('received_quantity', '>', 0)
        ->whereHas('purchaseOrder', function ($q) {
            $q->whereIn('status', [PurchaseOrder::STATUS_APPROVED]);
        });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                       ->orWhere('sku', 'like', "%{$search}%")
                       ->orWhereHas('category', function ($cq) use ($search) {
                           $cq->where('name', 'like', "%{$search}%")
                              ->orWhere('asset', 'like', "%{$search}%");
                       });
                })
                ->orWhereHas('purchaseOrder', function ($poq) use ($search) {
                    $poq->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', function ($vq) use ($search) {
                            $vq->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('purchaseRequest', function ($prq) use ($search) {
                            $prq->where('pr_number', 'like', "%{$search}%");
                        });
                });
            });
        }

        if ($branchId) {
            $query->whereHas('purchaseOrder', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        if ($asset) {
            $query->whereHas('product.category', function ($cq) use ($asset) {
                $cq->where('asset', $asset);
            });
        }

        if ($receiptType === 'full') {
            $query->whereColumn('received_quantity', '>=', 'quantity');
        } elseif ($receiptType === 'partial') {
            $query->whereColumn('received_quantity', '<', 'quantity');
        }

        if ($dateFrom) {
            $query->whereDate('received_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('received_at', '<=', $dateTo);
        }

        $items = $query->orderByRaw('COALESCE(received_at, updated_at) DESC')->get();

        $filename = 'daftar_barang_masuk_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($items) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['Tanggal Terima', 'Kode / SKU', 'Nama Barang', 'Kategori', 'Tipe Asset', 'Qty Diterima', 'Qty Dipesan', 'Satuan', 'Status', 'No. PO', 'No. PR', 'Vendor', 'Cabang']);

            foreach ($items as $item) {
                $receiveDate = $item->received_at 
                    ? $item->received_at->format('d/m/Y H:i') 
                    : ($item->purchaseOrder?->received_at ? $item->purchaseOrder->received_at->format('d/m/Y H:i') : '-');

                $status = ($item->received_quantity >= $item->quantity) ? 'Lengkap' : 'Sebagian';

                fputcsv($handle, [
                    $receiveDate,
                    $item->product?->sku ?? '-',
                    $item->product?->name ?? '-',
                    $item->product?->category?->name ?? '-',
                    $item->product?->category?->asset ?? '-',
                    $item->received_quantity,
                    $item->quantity,
                    $item->product?->unit ?? 'Pcs',
                    $status,
                    $item->purchaseOrder?->po_number ?? '-',
                    $item->purchaseOrder?->purchaseRequest?->pr_number ?? '-',
                    $item->purchaseOrder?->vendor?->name ?? '-',
                    $item->purchaseOrder?->branch?->name ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
