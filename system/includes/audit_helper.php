<?php

/**
 * Shared audit log helpers for both mysqli and PDO writers.
 * Keeps action/table/value/user/ip handling consistent.
 */

if (!function_exists('auditNormalizeValue')) {
    function auditNormalizeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return (string)$value;
        }

        return $json;
    }
}

if (!function_exists('auditActionLabel')) {
    function auditActionLabel(string $action): string
    {
        $label = str_replace(['_', ':'], ' ', trim($action));
        $label = preg_replace('/\s+/', ' ', $label ?? '');
        return ucwords((string)$label);
    }
}

if (!function_exists('auditDefaultMessage')) {
    function auditDefaultMessage(string $action, ?string $tableName, int $recordId): string
    {
        $parts = [auditActionLabel($action)];
        if (!empty($tableName)) {
            $parts[] = 'on ' . $tableName;
        }
        if ($recordId > 0) {
            $parts[] = '#' . $recordId;
        }

        return trim(implode(' ', $parts));
    }
}

if (!function_exists('auditBuildMeta')) {
    function auditBuildMeta(?int $userId = null, ?string $ipAddress = null): array
    {
        $meta = [];

        $requestMethod = trim((string)($_SERVER['REQUEST_METHOD'] ?? ''));
        if ($requestMethod !== '') {
            $meta['request_method'] = strtoupper($requestMethod);
        }

        $requestUri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($requestUri !== '') {
            $meta['request_uri'] = $requestUri;
        }

        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent !== '') {
            $meta['user_agent'] = substr($userAgent, 0, 500);
        }

        if ($userId !== null && $userId > 0) {
            $meta['actor_user_id'] = $userId;
        }

        if (!empty($_SESSION['admin_role'])) {
            $meta['actor_role'] = (string)$_SESSION['admin_role'];
        } elseif (!empty($_SESSION['user_role'])) {
            $meta['actor_role'] = (string)$_SESSION['user_role'];
        } elseif (!empty($_SESSION['role'])) {
            $meta['actor_role'] = (string)$_SESSION['role'];
        }

        $actorName = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? null;
        if (is_string($actorName) && trim($actorName) !== '') {
            $meta['actor_name'] = trim($actorName);
        }

        if (!empty($ipAddress)) {
            $meta['ip_address'] = $ipAddress;
        }

        return $meta;
    }
}

if (!function_exists('auditInferClassification')) {
    function auditInferClassification(string $action, ?string $tableName = null): array
    {
        $a = strtolower($action);
        $t = strtolower((string)$tableName);

        $severity = 'low';
        if (
            strpos($a, 'access_denied') !== false ||
            strpos($a, 'failed') !== false ||
            strpos($a, 'delete') !== false ||
            strpos($a, 'deactivate') !== false ||
            strpos($a, 'reject') !== false ||
            strpos($a, 'lock') !== false ||
            strpos($a, 'clear_old_logs') !== false
        ) {
            $severity = 'high';
        } elseif (
            strpos($a, 'update') !== false ||
            strpos($a, 'edit') !== false ||
            strpos($a, 'approve') !== false ||
            strpos($a, 'create') !== false ||
            strpos($a, 'status') !== false ||
            strpos($a, 'password') !== false
        ) {
            $severity = 'medium';
        }

        $category = 'system';
        if (strpos($a, 'order') !== false || $t === 'orders') {
            $category = 'orders';
        } elseif (strpos($a, 'supplier') !== false || $t === 'suppliers') {
            $category = 'suppliers';
        } elseif (
            strpos($a, 'inventory') !== false ||
            strpos($a, 'stock') !== false ||
            strpos($a, 'remedy') !== false ||
            $t === 'remedies'
        ) {
            $category = 'inventory';
        } elseif (
            strpos($a, 'user') !== false ||
            strpos($a, 'login') !== false ||
            strpos($a, 'access') !== false ||
            $t === 'users'
        ) {
            $category = 'users';
        } elseif ($t === 'audit_log' || strpos($a, 'audit') !== false) {
            $category = 'audit';
        }

        return ['severity' => $severity, 'category' => $category];
    }
}

if (!function_exists('auditBuildNewPayload')) {
    function auditBuildNewPayload(
        string $action,
        ?string $tableName,
        int $recordId,
        $newValues,
        ?int $userId = null,
        ?string $ipAddress = null
    ) {
        $payload = null;

        if (is_array($newValues)) {
            $payload = $newValues;
        } elseif (is_object($newValues)) {
            $payload = json_decode(json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
        } elseif (is_string($newValues)) {
            $trimmed = trim($newValues);
            if ($trimmed === '') {
                $payload = [];
            } else {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload = $decoded;
                } else {
                    $payload = ['message' => $newValues];
                }
            }
        } elseif ($newValues === null) {
            $payload = [];
        } else {
            $payload = ['value' => $newValues];
        }

        if (!is_array($payload)) {
            return $newValues;
        }

        if (!isset($payload['message']) || trim((string)$payload['message']) === '') {
            $payload['message'] = auditDefaultMessage($action, $tableName, $recordId);
        }
        if (!isset($payload['action'])) {
            $payload['action'] = $action;
        }
        if (!isset($payload['table']) && !empty($tableName)) {
            $payload['table'] = $tableName;
        }
        if (!isset($payload['record_id']) && $recordId > 0) {
            $payload['record_id'] = $recordId;
        }
        $classification = auditInferClassification($action, $tableName);
        if (!isset($payload['severity'])) {
            $payload['severity'] = $classification['severity'];
        }
        if (!isset($payload['category'])) {
            $payload['category'] = $classification['category'];
        }

        $existingMeta = [];
        if (isset($payload['_meta']) && is_array($payload['_meta'])) {
            $existingMeta = $payload['_meta'];
        }

        $payload['_meta'] = array_merge(
            auditBuildMeta($userId, $ipAddress),
            $existingMeta
        );

        return $payload;
    }
}

if (!function_exists('auditResolveUserId')) {
    function auditResolveUserId($userId = null): ?int
    {
        if ($userId === null) {
            $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        }

        if ($userId === null || $userId === '' || (int)$userId <= 0) {
            return null;
        }

        return (int)$userId;
    }
}

if (!function_exists('auditResolveIpAddress')) {
    function auditResolveIpAddress(?string $ipAddress = null): ?string
    {
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        if ($ip === null) {
            return null;
        }

        $ip = trim($ip);
        return $ip === '' ? null : $ip;
    }
}

if (!function_exists('auditLogMysqli')) {
    function auditLogMysqli(
        mysqli $conn,
        string $action,
        ?string $tableName = null,
        $recordId = null,
        $oldValues = null,
        $newValues = null,
        $userId = null,
        ?string $ipAddress = null
    ): bool {
        $action = trim($action);
        if ($action === '') {
            return false;
        }

        $tableName = $tableName !== null ? trim((string)$tableName) : null;
        $tableName = $tableName === '' ? null : $tableName;
        $recordId = $recordId === null ? 0 : (int)$recordId;
        $oldJson = auditNormalizeValue($oldValues);
        $resolvedUserId = auditResolveUserId($userId);
        $resolvedIp = auditResolveIpAddress($ipAddress);
        $newPayload = auditBuildNewPayload($action, $tableName, $recordId, $newValues, $resolvedUserId, $resolvedIp);
        $newJson = auditNormalizeValue($newPayload);

        $sql = "
            INSERT INTO audit_log
                (action, table_name, record_id, old_values, new_values, user_id, ip_address, created_at)
            VALUES
                (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NOW())
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('auditLogMysqli prepare failed: ' . $conn->error);
            return false;
        }

        $userParam = $resolvedUserId !== null ? (string)$resolvedUserId : '';
        $ipParam = $resolvedIp ?? '';

        $stmt->bind_param(
            'ssissss',
            $action,
            $tableName,
            $recordId,
            $oldJson,
            $newJson,
            $userParam,
            $ipParam
        );

        $ok = $stmt->execute();
        if (!$ok) {
            error_log('auditLogMysqli execute failed: ' . $stmt->error);
        }
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('auditLogPdo')) {
    function auditLogPdo(
        PDO $pdo,
        string $action,
        ?string $tableName = null,
        $recordId = null,
        $oldValues = null,
        $newValues = null,
        $userId = null,
        ?string $ipAddress = null
    ): bool {
        $action = trim($action);
        if ($action === '') {
            return false;
        }

        $tableName = $tableName !== null ? trim((string)$tableName) : null;
        $tableName = $tableName === '' ? null : $tableName;
        $recordId = $recordId === null ? 0 : (int)$recordId;
        $oldJson = auditNormalizeValue($oldValues);
        $resolvedUserId = auditResolveUserId($userId);
        $resolvedIp = auditResolveIpAddress($ipAddress);
        $newPayload = auditBuildNewPayload($action, $tableName, $recordId, $newValues, $resolvedUserId, $resolvedIp);
        $newJson = auditNormalizeValue($newPayload);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log
                    (action, table_name, record_id, old_values, new_values, user_id, ip_address, created_at)
                VALUES
                    (:action, :table_name, :record_id, :old_values, :new_values, :user_id, :ip_address, NOW())
            ");

            return $stmt->execute([
                ':action' => $action,
                ':table_name' => $tableName,
                ':record_id' => $recordId,
                ':old_values' => $oldJson,
                ':new_values' => $newJson,
                ':user_id' => $resolvedUserId,
                ':ip_address' => $resolvedIp,
            ]);
        } catch (Throwable $e) {
            error_log('auditLogPdo failed: ' . $e->getMessage());
            return false;
        }
    }
}
