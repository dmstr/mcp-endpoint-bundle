<?php

// file generated with AI assistance: Claude Code - 2026-10-31 20:00:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `mcp_endpoint.required_role` becomes `required_permission`. The default is
 * the permission `mcpendpoint:endpoint:use`, and the stored value `ROLE_USER`
 * is mapped to it. Other role values are left as they are: the bundle cannot
 * know what the application's roles mean, so the application's own migration
 * maps them and refuses to go on while a `ROLE_%` value remains.
 */
final class Version20261031200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'mcp_endpoint: required_role becomes required_permission (of dmstr/mcp-endpoint-bundle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mcp_endpoint RENAME COLUMN required_role TO required_permission');
        $this->addSql("ALTER TABLE mcp_endpoint ALTER COLUMN required_permission SET DEFAULT 'mcpendpoint:endpoint:use'");
        $this->addSql("UPDATE mcp_endpoint SET required_permission = 'mcpendpoint:endpoint:use' WHERE required_permission = 'ROLE_USER'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE mcp_endpoint SET required_permission = 'ROLE_USER' WHERE required_permission = 'mcpendpoint:endpoint:use'");
        $this->addSql("ALTER TABLE mcp_endpoint ALTER COLUMN required_permission SET DEFAULT 'ROLE_USER'");
        $this->addSql('ALTER TABLE mcp_endpoint RENAME COLUMN required_permission TO required_role');
    }
}
