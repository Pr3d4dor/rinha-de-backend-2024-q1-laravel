<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::unprepared('
            CREATE OR REPLACE FUNCTION create_transaction_trigger_function()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
              v_limite INTEGER;
              v_saldo INTEGER;
            BEGIN
              IF NEW.valor < 0 THEN
                RAISE EXCEPTION \'Transaction amount cannot be negative!\';
              END IF;

              SELECT limite, saldo INTO v_limite, v_saldo FROM clientes WHERE id = NEW.cliente_id;

              IF NEW.tipo = \'c\' THEN
                UPDATE clientes SET saldo = saldo + NEW.valor WHERE id = NEW.cliente_id;
              ELSIF NEW.tipo = \'d\' THEN
                IF (v_saldo + v_limite - NEW.valor) < 0 THEN
                  RAISE EXCEPTION \'Debit exceeds customer limit and balance!\';
                ELSE
                  UPDATE clientes SET saldo = saldo - NEW.valor WHERE id = NEW.cliente_id;
                END IF;
              ELSE
                RAISE EXCEPTION \'Invalid transaction!\';
              END IF;

              RETURN NEW;
            END;
            $$;
        ');

        DB::unprepared('
            CREATE TRIGGER create_transaction_trigger
            BEFORE INSERT
            ON transacoes
            FOR EACH ROW
            EXECUTE FUNCTION create_transaction_trigger_function();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS create_transaction_trigger ON transacoes');

        DB::unprepared('DROP FUNCTION IF EXISTS create_transaction_trigger_function');
    }
};
