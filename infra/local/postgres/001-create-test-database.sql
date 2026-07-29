SELECT 'CREATE ROLE global_english_test LOGIN PASSWORD ''global_english_test'''
WHERE NOT EXISTS (
    SELECT 1
    FROM pg_roles
    WHERE rolname = 'global_english_test'
)\gexec

SELECT 'CREATE DATABASE global_english_test OWNER global_english_test'
WHERE NOT EXISTS (
    SELECT 1
    FROM pg_database
    WHERE datname = 'global_english_test'
)\gexec
