<?php

function table_exists($conn, $table_name)
{
    try {
        $check = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
        );
        $check->execute([$table_name]);
        $result = $check->fetch(PDO::FETCH_ASSOC);
        return isset($result["total"]) && (int) $result["total"] > 0;
    } catch (Exception $e) {
        return false;
    }
}

function db_guard_warn_missing($missing_tables)
{
    $env = getenv("APP_ENV") ?: "";
    if ($env !== "local") {
        return;
    }

    foreach ($missing_tables as $table_name) {
        echo '
      <div class="message">
         <span>Database schema incomplete: ' .
            $table_name .
            ' table missing</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
    }
}

?>
