<?php

namespace Tests\Unit;

use App\Support\DatabaseBinary;
use PHPUnit\Framework\TestCase;

class DatabaseBinaryTest extends TestCase
{
    public function test_it_encodes_binary_content_for_postgresql_bytea(): void
    {
        $binary = "\xFF\x00PDF";

        $encoded = DatabaseBinary::encode($binary, 'pgsql');

        $this->assertSame('\\xff00504446', strtolower($encoded));
    }

    public function test_it_keeps_binary_content_unchanged_for_non_postgresql_drivers(): void
    {
        $binary = "\xFF\x00PNG";

        $this->assertSame($binary, DatabaseBinary::encode($binary, 'sqlite'));
    }

    public function test_it_decodes_hex_encoded_bytea_content(): void
    {
        $binary = "\xFF\x00PDF";

        $this->assertSame($binary, DatabaseBinary::decode('\\xff00504446'));
    }

    public function test_it_returns_plain_string_content_unchanged(): void
    {
        $this->assertSame('avatar-content', DatabaseBinary::decode('avatar-content'));
    }
}
