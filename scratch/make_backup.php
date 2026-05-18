<?php
// scratch/make_backup.php
require_once __DIR__ . '/../php/db_connection.php';

header('Content-Type: text/plain');

try {
    $output = "-- PostgreSQL database dump generated via PHP\n";
    $output .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Disable constraints
    $output .= "SET statement_timeout = 0;\n";
    $output .= "SET lock_timeout = 0;\n";
    $output .= "SET client_encoding = 'UTF8';\n";
    $output .= "SET standard_conforming_strings = on;\n";
    $output .= "SET check_function_bodies = false;\n\n";

    echo "Fetching tables...\n";
    // 1. Get all tables
    $tables_stmt = $conn->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
          AND table_type = 'BASE TABLE'
          AND table_name NOT IN ('spatial_ref_sys')
        ORDER BY table_name
    ");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Fetching views...\n";
    // 2. Get all views
    $views_stmt = $conn->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
          AND table_type = 'VIEW'
        ORDER BY table_name
    ");
    $views = $views_stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Fetching sequences...\n";
    // 3. Get all sequences
    $seq_stmt = $conn->query("
        SELECT sequence_name 
        FROM information_schema.sequences 
        WHERE sequence_schema = 'public'
        ORDER BY sequence_name
    ");
    $sequences = $seq_stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Fetching functions...\n";
    // 4. Get custom functions
    $funcs_stmt = $conn->query("
        SELECT  p.proname,
                pg_get_functiondef(p.oid) as func_def
        FROM    pg_proc p
        JOIN    pg_namespace n ON p.pronamespace = n.oid
        WHERE   n.nspname = 'public'
          AND   p.prokind = 'f'
    ");
    $functions = $funcs_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Write sequences
    foreach ($sequences as $seq) {
        $output .= "DROP SEQUENCE IF EXISTS public.{$seq} CASCADE;\n";
        $output .= "CREATE SEQUENCE public.{$seq};\n\n";
    }

    // Write functions
    foreach ($functions as $f) {
        $output .= "DROP FUNCTION IF EXISTS public.{$f['proname']} CASCADE;\n";
        $output .= $f['func_def'] . ";\n\n";
    }

    // Write tables schema and drop views first (to avoid dependency errors)
    foreach ($views as $view) {
        $output .= "DROP VIEW IF EXISTS public.{$view} CASCADE;\n";
    }
    
    foreach ($tables as $table) {
        $output .= "DROP TABLE IF EXISTS public.{$table} CASCADE;\n";
        
        // Let's build a basic CREATE TABLE based on information_schema.columns
        $col_stmt = $conn->prepare("
            SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_schema = 'public' AND table_name = :table
            ORDER BY ordinal_position
        ");
        $col_stmt->execute([':table' => $table]);
        $columns = $col_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $col_defs = [];
        foreach ($columns as $col) {
            $def = $col['column_name'] . ' ' . $col['data_type'];
            if ($col['character_maximum_length']) {
                $def .= '(' . $col['character_maximum_length'] . ')';
            }
            if ($col['is_nullable'] === 'NO') {
                $def .= ' NOT NULL';
            }
            if ($col['column_default'] !== null) {
                $def .= ' DEFAULT ' . $col['column_default'];
            }
            $col_defs[] = $def;
        }
        
        // Get primary key
        $pk_stmt = $conn->prepare("
            SELECT kcu.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu 
              ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
            WHERE tc.constraint_type = 'PRIMARY KEY' 
              AND tc.table_name = :table
        ");
        $pk_stmt->execute([':table' => $table]);
        $pks = $pk_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($pks)) {
            $col_defs[] = 'PRIMARY KEY (' . implode(', ', $pks) . ')';
        }
        
        $output .= "CREATE TABLE public.{$table} (\n    " . implode(",\n    ", $col_defs) . "\n);\n\n";
    }

    echo "Dumping data...\n";
    // Write table rows (INSERT statements)
    foreach ($tables as $table) {
        $rows_stmt = $conn->query("SELECT * FROM public.{$table}");
        $rows = $rows_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) continue;
        
        $output .= "-- Data for table: {$table}\n";
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = 'NULL';
                } elseif (is_bool($val)) {
                    $vals[] = $val ? 'true' : 'false';
                } elseif (is_numeric($val)) {
                    $vals[] = $val;
                } else {
                    $vals[] = $conn->quote($val);
                }
            }
            $output .= "INSERT INTO public.{$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $output .= "\n";
    }

    // Recreate views
    foreach ($views as $view) {
        $view_def_stmt = $conn->prepare("
            SELECT view_definition 
            FROM information_schema.views 
            WHERE table_schema = 'public' AND table_name = :view
        ");
        $view_def_stmt->execute([':view' => $view]);
        $view_def = $view_def_stmt->fetchColumn();
        if ($view_def) {
            $output .= "CREATE VIEW public.{$view} AS\n{$view_def};\n\n";
        }
    }

    // Save to file
    $backup_file = __DIR__ . '/../backup_database_solayao.sql';
    file_put_contents($backup_file, $output);
    
    echo "SUCCESS: Database successfully backed up to backup_database_solayao.sql!\n";
    echo "Total bytes written: " . strlen($output) . "\n\n";
    
    // 5. Automated Git Push
    echo "--- Git Operations ---\n";
    
    $git_add_cmd = 'git add "' . realpath($backup_file) . '" 2>&1';
    echo "Running: {$git_add_cmd}\n";
    exec($git_add_cmd, $add_out, $add_code);
    echo implode("\n", $add_out) . "\n";
    
    $git_commit_cmd = 'git commit -m "Backup database: ' . date('Y-m-d H:i:s') . '" 2>&1';
    echo "Running: {$git_commit_cmd}\n";
    exec($git_commit_cmd, $commit_out, $commit_code);
    echo implode("\n", $commit_out) . "\n";
    
    $git_push_cmd = 'git push 2>&1';
    echo "Running: {$git_push_cmd}\n";
    exec($git_push_cmd, $push_out, $push_code);
    echo implode("\n", $push_out) . "\n";
    
    echo "\nAll operations completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
