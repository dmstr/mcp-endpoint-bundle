<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table `mcp_endpoint` of {@see \Dmstr\McpEndpoint\Entity\McpEndpoint}:
 * one MCP endpoint profile per slug. `tools`, `scope` and `retrieval` are
 * JSON; scope ids are not foreign keys (a deleted target leaves a dead id).
 */
final class Version20261008223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mcp_endpoint (of dmstr/mcp-endpoint-bundle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE mcp_endpoint (id UUID NOT NULL, slug VARCHAR(100) NOT NULL, name VARCHAR(190) NOT NULL, description TEXT NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, tools JSON NOT NULL, read_only BOOLEAN DEFAULT true NOT NULL, required_role VARCHAR(100) DEFAULT 'ROLE_USER' NOT NULL, scope JSON NOT NULL, retrieval JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_mcp_endpoint_slug ON mcp_endpoint (slug)');
        $this->addSql("COMMENT ON COLUMN mcp_endpoint.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN mcp_endpoint.created_at IS '(DC2Type:datetimetz_immutable)'");
        $this->addSql("COMMENT ON COLUMN mcp_endpoint.updated_at IS '(DC2Type:datetimetz_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mcp_endpoint');
    }
}
