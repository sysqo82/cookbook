<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table for admin authentication and seed initial admin account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_users_username (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $hashedPassword = '$argon2id$v=19$m=65536,t=4,p=1$Wi5uVVNhV2plMC9ZQm9Faw$eSKxAQX1jLJ+RMpkyhTaUnedMF/+V3V9ecRYoEU2D60';
        $this->addSql(
            'INSERT INTO users (username, roles, password, created_at) VALUES (:username, :roles, :password, NOW())',
            [
                'username' => 'admin',
                'roles' => json_encode(['ROLE_ADMIN'], JSON_THROW_ON_ERROR),
                'password' => $hashedPassword,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
