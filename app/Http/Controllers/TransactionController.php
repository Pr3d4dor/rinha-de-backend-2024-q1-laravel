<?php

namespace App\Http\Controllers;

use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    private TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index(string $id)
    {
        $now = Carbon::now();

        $customer = $this->transactionService->getCustomer(intval($id));
        if (! $customer) {
            return response('', Response::HTTP_NOT_FOUND);
        }

        $lastTransactions = $this->transactionService->getLastTransactions($customer->id);

        return response()->json([
            'saldo' => [
                'total' => intval($customer->saldo),
                'data_extrato' => $now->format('Y-m-d\TH:i:s\Z'),
                'limite' => intval($customer->limite),
            ],
            'ultimas_transacoes' => $lastTransactions,
        ]);
    }

    public function store(string $id, Request $request)
    {
        $validated = $this->validate(
            $request,
            [
                'valor' => ['required', 'integer', 'gt:0'],
                'tipo' => ['required', Rule::in(['c', 'd'])],
                'descricao' => ['required', 'min:1', 'max:10'],
            ],
        );

        $customer = $this->transactionService->getCustomer(intval($id));
        if (! $customer) {
            return response('', Response::HTTP_NOT_FOUND);
        }

        $amount = $validated['valor'];
        $type = $validated['tipo'];
        $description = $validated['descricao'];

        try {
            $this->transactionService->create(
                intval($customer->id),
                intval($amount),
                $type,
                $description,
            );

            $updatedCustomer = $this->transactionService->getCustomer(intval($id));

            return response()->json([
                'limite' => intval($customer->limite),
                'saldo' => intval($updatedCustomer->saldo),
            ]);
        } catch (\Exception $e) {
            return response()->json('', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
