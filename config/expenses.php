<?php

return [
    'proof_disk' => env('EXPENSE_PROOF_DISK', 'expense_proofs'),
    'proof_retention_days' => (int) env('EXPENSE_PROOF_RETENTION_DAYS', 365),
    'cleanup_retry_minutes' => (int) env('EXPENSE_PROOF_CLEANUP_RETRY_MINUTES', 15),
    'cleanup_batch_size' => (int) env('EXPENSE_PROOF_CLEANUP_BATCH_SIZE', 100),
    'cleanup_claim_timeout_minutes' => (int) env('EXPENSE_PROOF_CLEANUP_CLAIM_TIMEOUT_MINUTES', 30),
    'orphan_scan_grace_minutes' => (int) env('EXPENSE_PROOF_ORPHAN_GRACE_MINUTES', 1440),
];
