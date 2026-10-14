<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_contract_check');
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_username_contract_check CHECK ((username IS NULL AND username_established_at IS NULL) OR (username IS NOT NULL AND username_established_at IS NOT NULL AND username = lower(username) AND (username ~ '^[a-z][a-z0-9._-]{3,63}$' OR username ~ '^\\+?[0-9]{7,20}$')))",
        );
        DB::statement('ALTER TABLE password_credentials DROP CONSTRAINT IF EXISTS password_credentials_contract_check');
        DB::statement(
            "ALTER TABLE password_credentials ADD CONSTRAINT password_credentials_contract_check CHECK (algorithm IN ('argon2id', 'bcrypt') AND policy_version > 0 AND credential_version > 0)",
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_contract_check');
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_username_contract_check CHECK ((username IS NULL AND username_established_at IS NULL) OR (username IS NOT NULL AND username_established_at IS NOT NULL AND username = lower(username) AND username ~ '^[a-z][a-z0-9._-]{3,63}$'))",
        );
        DB::statement('ALTER TABLE password_credentials DROP CONSTRAINT IF EXISTS password_credentials_contract_check');
        DB::statement(
            "ALTER TABLE password_credentials ADD CONSTRAINT password_credentials_contract_check CHECK (algorithm = 'argon2id' AND policy_version > 0 AND credential_version > 0)",
        );
    }
};
