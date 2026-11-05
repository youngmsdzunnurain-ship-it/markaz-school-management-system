-- Remove only the retired, unused administrator result print-scale preference.
-- The printable report layout uses its own responsive page sizing and never reads this key.
START TRANSACTION;
DELETE FROM settings WHERE setting_key = 'result_print_scale';
COMMIT;
