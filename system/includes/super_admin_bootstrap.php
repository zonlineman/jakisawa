<?php

if (!function_exists('getReservedSuperAdminEmail')) {
    function getReservedSuperAdminEmail(): string
    {
        return 'johnarumansi@gmail.com';
    }
}

if (!function_exists('getReservedSuperAdminUsername')) {
    function getReservedSuperAdminUsername(): string
    {
        return 'johnarumansi';
    }
}

if (!function_exists('getReservedSuperAdminName')) {
    function getReservedSuperAdminName(): string
    {
        return 'John Arumansi';
    }
}

if (!function_exists('getReservedSuperAdminPassword')) {
    function getReservedSuperAdminPassword(): string
    {
        return '#@Jaki,2026';
    }
}

if (!function_exists('isReservedSuperAdminEmail')) {
    function isReservedSuperAdminEmail(string $email): bool
    {
        return strtolower(trim($email)) === strtolower(getReservedSuperAdminEmail());
    }
}

if (!function_exists('getUsersColumnMap')) {
    function getUsersColumnMap(PDO $pdo): array
    {
        $map = [];
        $rows = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $field = strtolower(trim((string)($row['Field'] ?? '')));
            if ($field !== '') {
                $map[$field] = (string)$row['Field'];
            }
        }
        return $map;
    }
}

if (!function_exists('ensureReservedSuperAdminAccount')) {
    function ensureReservedSuperAdminAccount(PDO $pdo): void
    {
        try {
            $columns = getUsersColumnMap($pdo);
            if (!isset($columns['email']) || !isset($columns['password_hash'])) {
                return;
            }

            $email = getReservedSuperAdminEmail();
            $passwordHash = password_hash(getReservedSuperAdminPassword(), PASSWORD_DEFAULT);

            $findStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $findStmt->execute([$email]);
            $existing = $findStmt->fetch(PDO::FETCH_ASSOC);

            $values = [
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => 'super_admin',
                'is_super_admin' => 1,
                'cannot_delete' => 1,
                'is_active' => 1,
                'status' => 'active',
                'full_name' => getReservedSuperAdminName(),
                'username' => getReservedSuperAdminUsername(),
                'email_verified' => 1,
                'verification_token' => null
            ];

            if ($existing && isset($existing['id'])) {
                $setParts = [];
                $params = [];

                foreach ($values as $key => $val) {
                    if (!isset($columns[$key])) {
                        continue;
                    }
                    $setParts[] = "`{$columns[$key]}` = ?";
                    $params[] = $val;
                }

                if (isset($columns['updated_at'])) {
                    $setParts[] = "`{$columns['updated_at']}` = NOW()";
                }

                if (empty($setParts)) {
                    return;
                }

                $params[] = (int)$existing['id'];
                $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ? LIMIT 1";
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute($params);
                return;
            }

            $insertCols = [];
            $insertMarks = [];
            $insertValues = [];

            foreach ($values as $key => $val) {
                if (!isset($columns[$key])) {
                    continue;
                }
                $insertCols[] = "`{$columns[$key]}`";
                $insertMarks[] = '?';
                $insertValues[] = $val;
            }

            if (isset($columns['created_at'])) {
                $insertCols[] = "`{$columns['created_at']}`";
                $insertMarks[] = 'NOW()';
            }

            if (isset($columns['updated_at'])) {
                $insertCols[] = "`{$columns['updated_at']}`";
                $insertMarks[] = 'NOW()';
            }

            if (empty($insertCols)) {
                return;
            }

            $insertSql = "INSERT INTO users (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertMarks) . ")";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute($insertValues);
        } catch (Throwable $e) {
            error_log('ensureReservedSuperAdminAccount failed: ' . $e->getMessage());
        }
    }
}

