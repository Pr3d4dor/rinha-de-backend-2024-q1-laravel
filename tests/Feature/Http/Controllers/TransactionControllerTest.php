<?php

namespace Tests\Feature\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * @test
     * @dataProvider customerDataProvider
     */
    public function it_can_get_current_balance_and_list_last_transactions_with_empty_data(array $customer): void
    {
        $this->artisan('db:seed');

        $now = Carbon::now();

        $response = $this->getJson(
            sprintf('/clientes/%d/extrato', $customer['id'])
        );

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'saldo' => [
                'total',
                'data_extrato',
                'limite'
            ],
            'ultimas_transacoes',
        ]);

        $this->assertEquals(0, $response->json('saldo.total'));
        $this->assertEquals(
            $now->format('Y-m-d\TH:i:s\Z'),
            $response->json('saldo.data_extrato')
        );
        $this->assertEquals(
            intval($customer['limite']),
            $response->json('saldo.limite')
        );
        $this->assertEmpty($response->json('ultimas_transacoes'));
    }

    /**
     * @test
     * @dataProvider customerDataProvider
     */
    public function it_can_get_current_balance_and_list_last_transactions(array $customer): void
    {
        $this->artisan('db:seed');

        $transactions = [];
        for ($i = 0; $i < 15; $i++) {
            $transactions[] = [
                'cliente_id' => $customer['id'],
                'valor' => 500 * 100,
                'tipo' => 'c',
                'descricao' => "P $i",
                'realizada_em' => '2024-02-19 00:00:01',
            ];
            $transactions[] = [
                'cliente_id' => $customer['id'],
                'valor' => 200 * 100,
                'tipo' => 'd',
                'descricao' => "W $i",
                'realizada_em' => '2024-02-19 00:00:01',
            ];
        }

        DB::table('transacoes')
            ->insert($transactions);

        DB::table('clientes')
            ->where('id', $customer['id'])
            ->update([
                'saldo' => 300 * 100
            ]);

        $now = Carbon::now();

        $response = $this->getJson(
            sprintf('/clientes/%d/extrato', $customer['id'])
        );

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'saldo' => [
                'total',
                'data_extrato',
                'limite'
            ],
            'ultimas_transacoes' => [
                [
                    'valor',
                    'tipo',
                    'descricao',
                    'realizada_em',
                ]
            ],
        ]);

        $this->assertEquals(300 * 100, $response->json('saldo.total'));
        $this->assertEquals(
            $now->format('Y-m-d\TH:i:s\Z'),
            $response->json('saldo.data_extrato')
        );
        $this->assertEquals(intval($customer['limite']), $response->json('saldo.limite'));
        $this->assertEquals(
            collect($transactions)
                ->reverse()
                ->take(10)
                ->map(function (array $transaction) {
                    return [
                        'valor' => $transaction['valor'],
                        'tipo' => $transaction['tipo'],
                        'descricao' => $transaction['descricao'],
                        'realizada_em' => '2024-02-19T00:00:01Z',
                    ];
                })
                ->values()
                ->toArray(),
            $response->json('ultimas_transacoes'),
        );
        $this->assertCount(10, $response->json('ultimas_transacoes'));
    }

    /** @test */
    public function it_returns_not_found_for_inexistent_customer_to_get_current_balance_and_list_last_transactions(): void
    {
        $this->artisan('db:seed');

        $response = $this->getJson('/clientes/6/extrato');

        $response->assertNotFound();
        $this->assertEmpty($response->getContent());
    }

    /**
     * @test
     * @dataProvider customerDataProvider
     */
    public function it_can_create_a_credit_transaction(array $customer): void
    {
        $this->artisan('db:seed');

        $this->assertDatabaseEmpty('transacoes');

        $transaction = [
            'valor' => 100 * 10,
            'tipo' => 'c',
            'descricao' => 'test',
        ];

        $response = $this->postJson(
            sprintf('/clientes/%d/transacoes', $customer['id']),
            $transaction,
        );

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'limite',
            'saldo',
        ]);
        $this->assertEquals($customer['limite'], $response->json('limite'));
        $this->assertEquals(100 * 10, $response->json('saldo'));
        $this->assertDatabaseHas(
            'transacoes',
            $transaction + ['cliente_id' => $customer['id']]
        );
    }

    /**
     * @test
     * @dataProvider customerDataProvider
     */
    public function it_can_create_a_debit_transaction(array $customer): void
    {
        $this->artisan('db:seed');

        $this->assertDatabaseEmpty('transacoes');

        $transaction = [
            'valor' => 100 * 10,
            'tipo' => 'd',
            'descricao' => 'test',
        ];

        $response = $this->postJson(
            sprintf('/clientes/%d/transacoes', $customer['id']),
            $transaction,
        );

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'limite',
            'saldo',
        ]);
        $this->assertEquals($customer['limite'], $response->json('limite'));
        $this->assertEquals(-100 * 10, $response->json('saldo'));
        $this->assertDatabaseHas(
            'transacoes',
            $transaction + ['cliente_id' => $customer['id']]
        );
    }

    /** @test */
    public function it_returns_not_found_for_inexistent_customer_to_create_a_transaction()
    {
        $this->artisan('db:seed');

        $this->assertDatabaseEmpty('transacoes');

        $transaction = [
            'valor' => 100 * 10,
            'tipo' => 'c',
            'descricao' => 'test',
        ];

        $response = $this->postJson(
            '/clientes/6/transacoes',
            $transaction,
        );

        $response->assertNotFound();
        $this->assertDatabaseEmpty('transacoes');
    }

    /**
     * @test
     * @dataProvider invalidPayloadDataProvider
     */
    public function it_returns_unprocessable_entity_when_payload_is_invalid(array $transaction)
    {
        $this->artisan('db:seed');

        $this->assertDatabaseEmpty('transacoes');

        $response = $this->postJson(
            '/clientes/1/transacoes',
            $transaction,
        );

        $response->assertUnprocessable();
        $this->assertDatabaseEmpty('transacoes');
    }

    public static function customerDataProvider()
    {
        return [
            [
                [
                    'id' => 1,
                    'nome' => 'o barato sai caro',
                    'limite' => 1000 * 100
                ]
            ],
            [
                [
                    'id' => 2,
                    'nome' => 'zan corp ltda',
                    'limite' => 800 * 100
                ]
            ],
            [
                [
                    'id' => 3,
                    'nome' => 'les cruders',
                    'limite' => 10000 * 100
                    ]
            ],
            [
                [
                    'id' => 4,
                    'nome' => 'padaria joia de cocaia',
                    'limite' => 100000 * 100
                ]
            ],
            [
                [
                    'id' => 5,
                    'nome' => 'kid mais',
                    'limite' => 5000 * 100
                ]
            ]
        ];
    }

    public static function invalidPayloadDataProvider()
    {
        return [
            [
                [
                    'valor' => -1,
                    'tipo' => 'c',
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'valor' => 1,
                    'tipo' => 'a',
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'valor' => 1.2,
                    'tipo' => 'c',
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'valor' => 1,
                    'tipo' => 'c',
                    'descricao' => ''
                ],
            ],
            [
                [
                    'valor' => -1,
                    'tipo' => 'c',
                    'descricao' => null,
                ],
            ],
            [
                [
                    'valor' => 1,
                    'tipo' => null,
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'valor' => null,
                    'tipo' => 'c',
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'valor' => 0,
                    'tipo' => 'c',
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'valor' => 1,
                    'tipo' => 'c',
                ],
            ],
            [
                [
                    'valor' => 1,
                ],
            ],
            [
                [
                ],
            ],
            [
                [
                    'valor' => 1,
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'tipo' => 'c',
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'descricao' => 'test'
                ],
            ],
            [
                [
                    'tipo' => 'c',
                ],
            ]
        ];
    }
}
