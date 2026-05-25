USE it_asset_db;
UPDATE users SET password='$2y$12$eTualBAnaF3vIU5JxGgYPu7SJ6szaLMxCEmQb5g38E05S3CpEGn0y' WHERE username IN ('admin','staff','viewer');
