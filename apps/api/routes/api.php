<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AfterSaleController;
use App\Http\Controllers\ClinicalPdfController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LacExamController;
use App\Http\Controllers\LacScheduleController;
use App\Http\Controllers\LabelPrintController;
use App\Http\Controllers\LabelTemplateController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CommunicationTemplateController;
use App\Http\Controllers\CommunicationLogController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrescriptionOcrController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SumUpController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AiAnalysisController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Middleware\CheckFeatureActive;
use App\Http\Middleware\EnforceSessionLimit;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Theja
|--------------------------------------------------------------------------
|
| Middleware chain per le route tenant-aware:
|   auth:sanctum → ResolveTenant → EnforceSessionLimit → [check.feature:X]
|
*/

// ─── Pubbliche (nessuna auth) ─────────────────────────────────────────────────

Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// Webhook Stripe — nessun middleware auth (Stripe usa firma HMAC, non token)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// ─── Autenticazione ───────────────────────────────────────────────────────────

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/select-pos', [AuthController::class, 'selectPos']);
    Route::post('/auth/logout',     [AuthController::class, 'logout']);
    Route::get('/auth/me',          [AuthController::class, 'me']);

    Route::get('/sessions',           [SessionController::class, 'index']);
    Route::delete('/sessions/{id}',   [SessionController::class, 'destroy']);
});

// ─── Route tenant-aware ──────────────────────────────────────────────────────
// auth:sanctum → ResolveTenant (switcha schema PostgreSQL) → EnforceSessionLimit

Route::middleware(['auth:sanctum', ResolveTenant::class, EnforceSessionLimit::class])->group(function () {

    // Usata nei test di ResolveTenant — restituisce il search_path corrente
    Route::get('/tenant/schema', function () {
        $row = DB::selectOne('SHOW search_path');
        return response()->json(['search_path' => $row->search_path ?? '']);
    });

    // Esempio route con feature flag — protetta da check.feature:ai_analysis_enabled
    Route::middleware([CheckFeatureActive::class . ':ai_analysis_enabled'])->group(function () {
        Route::get('/ai/analyze', fn () => response()->json(['status' => 'ai_ready']));
    });

    // ─── Pazienti, prescrizioni, LAC (Fase 2) ───────────────────────────────
    Route::get('/users', [UserController::class, 'index']);

    Route::apiResource('patients', PatientController::class);

    Route::post('patients/{patient}/prescriptions/ocr', [PrescriptionOcrController::class, 'store']);
    Route::get('patients/{patient}/prescriptions/{prescription}/pdf', [ClinicalPdfController::class, 'prescriptionPdf']);
    Route::get('patients/{patient}/lac-exams/{lacExam}/pdf', [ClinicalPdfController::class, 'lacExamPdf']);

    Route::get('/prescriptions', [PrescriptionController::class, 'index']);
    Route::post('/prescriptions', [PrescriptionController::class, 'store']);
    Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show']);
    Route::put('/prescriptions/{prescription}', [PrescriptionController::class, 'update']);

    Route::get('/lac-exams', [LacExamController::class, 'index']);
    Route::post('/lac-exams', [LacExamController::class, 'store']);
    Route::get('/lac-exams/{lacExam}', [LacExamController::class, 'show']);
    Route::put('/lac-exams/{lacExam}', [LacExamController::class, 'update']);

    // ─── Magazzino (Fase 3) ────────────────────────────────────────────────
    Route::apiResource('suppliers', SupplierController::class);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'lookupByBarcode']);
    Route::post('/products/{product}/generate-barcode', [ProductController::class, 'generateBarcode']);
    Route::get('/products/{product}/barcode.svg', [ProductController::class, 'barcodeSvg']);
    Route::apiResource('products', ProductController::class);

    Route::apiResource('label-templates', LabelTemplateController::class);
    Route::post('/labels/print', [LabelPrintController::class, 'print'])->middleware('throttle:20,1');

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/update-stock', [InventoryController::class, 'updateStock']);
    Route::get('/inventory/movements', [InventoryController::class, 'movements']);

    Route::get('/stock-movements', [StockMovementController::class, 'index']);
    Route::post('/stock-movements', [StockMovementController::class, 'store']);

    Route::get('/stock-transfers', [StockTransferController::class, 'index']);
    Route::post('/stock-transfers/request', [StockTransferController::class, 'requestTransfer']);
    Route::post('/stock-transfers/{transfer}/accept', [StockTransferController::class, 'accept']);
    Route::post('/stock-transfers/{transfer}/reject', [StockTransferController::class, 'reject']);
    Route::post('/stock-transfers/{transfer}/complete', [StockTransferController::class, 'complete']);

    Route::get('/lac-schedules', [LacScheduleController::class, 'index']);

    // ─── Vendite e Ordini (Fase 4) ───────────────────────────────────────
    Route::apiResource('sales', SaleController::class);
    Route::post('/sales/{sale}/payments', [SaleController::class, 'addPayment']);
    Route::post('/sales/{sale}/schedule-payments', [SaleController::class, 'schedulePayments']);
    Route::post('/sales/{sale}/deliver', [SaleController::class, 'deliver']);
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::get('/sales/{sale}/payment-summary', [SaleController::class, 'paymentSummary']);

    Route::get('/orders/pending', [OrderController::class, 'pending']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    Route::get('/after-sale-events', [AfterSaleController::class, 'index']);
    Route::post('/after-sale-events', [AfterSaleController::class, 'store']);
    Route::post('/after-sale-events/{afterSaleEvent}/status', [AfterSaleController::class, 'updateStatus']);

    // ─── Fatturazione (Fase 5) ─────────────────────────────────────────────
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);

    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
    Route::post('/invoices/{invoice}/send-sdi', [InvoiceController::class, 'sendSdi']);

    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::get('/invoices/{invoice}/xml', [InvoiceController::class, 'xml']);

    // ─── Agenda e comunicazioni (Fase 6) ─────────────────────────────────────
    Route::get('/appointments/calendar', [AppointmentController::class, 'calendar']);
    Route::get('/appointments/today', [AppointmentController::class, 'today']);
    Route::apiResource('appointments', AppointmentController::class);

    Route::apiResource('communication-templates', CommunicationTemplateController::class);
    Route::get('/communication-logs', [CommunicationLogController::class, 'index']);
    Route::get('/communication-logs/{communicationLog}', [CommunicationLogController::class, 'show']);

    // ─── Cassa virtuale e pagamenti (Fase 7) ──────────────────────────────────
    Route::get('/cash-register/session', [CashRegisterController::class, 'session']);
    Route::post('/cash-register/open', [CashRegisterController::class, 'open']);
    Route::post('/cash-register/close', [CashRegisterController::class, 'close']);
    Route::get('/cash-register/summary', [CashRegisterController::class, 'summary']);
    Route::post('/cash-register/fiscal-document', [CashRegisterController::class, 'fiscalDocument']);

    Route::post('/payments/sumup/create', [SumUpController::class, 'create']);
    Route::get('/payments/sumup/{id}/status', [SumUpController::class, 'status']);
    Route::post('/payments/sumup/{id}/refund', [SumUpController::class, 'refund']);

    // ─── Reportistica + AI Analysis (Fase 8) ────────────────────────────────────
    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/inventory', [ReportController::class, 'inventory']);
    Route::get('/reports/patients', [ReportController::class, 'patients']);
    Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/reports/top-products', [ReportController::class, 'topProducts']);
    Route::get('/reports/org-aggregate', [ReportController::class, 'orgAggregate']);

    Route::post('/reports/query-builder', [ReportController::class, 'queryBuilder']);

    Route::middleware('ai.tenant.throttle')->group(function () {
        Route::get('/ai/trends', [AiAnalysisController::class, 'trends']);
        Route::get('/ai/forecast-reorders', [AiAnalysisController::class, 'forecastReorders']);
        Route::get('/ai/revenue-analysis', [AiAnalysisController::class, 'revenueAnalysis']);
        Route::get('/ai/opportunities', [AiAnalysisController::class, 'opportunities']);
    });

// ─── Admin globale (super_admin) ─────────────────────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/admin/organizations', [AdminController::class, 'organizations']);
        Route::post('/admin/organizations', [AdminController::class, 'storeOrganization']);
        Route::put('/admin/organizations/{org}/features', [AdminController::class, 'updateFeatures']);
        Route::get('/admin/stats', [AdminController::class, 'stats']);
    });
});
});
