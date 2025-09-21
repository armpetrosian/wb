<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Repositories\SaleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    protected $saleRepository;

    public function __construct(SaleRepository $saleRepository)
    {
        $this->saleRepository = $saleRepository;
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $accountId = auth()->user()->account_id;
        $days = $request->input('days', 7);

        $sales = $this->saleRepository->getFreshSales($accountId, $days);

        return response()->json($sales);
    }

    public function show($id)
    {
        $sale = Sale::findOrFail($id);

        if (Gate::denies('view', $sale)) {
            abort(403, 'У вас нет прав на просмотр этой записи');
        }

        return response()->json($sale);
    }

    public function byPeriod(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $accountId = auth()->user()->account_id;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $sales = $this->saleRepository->getSalesByPeriod($accountId, $startDate, $endDate);

        return response()->json($sales);
    }
}
