# Health gate hardening — qa2 staging only

- تنها تغییر رفتار: بررسی وجود ستون‌های جدول `financial_commit_keys`، یکتایی PRIMARY بر `client_token` و نسخه `schema_migrations` در تست سلامت Update.
- نسخه اپلیکیشن: `2.6.19-dev.25-qa2`؛ نسخه Schema همان `2.6.0-rc7-qa1` است.
- این نسخه فقط برای نصب Staging است؛ نه Update/Production.
- نیازمند آزمون MariaDB/Restore/UAT واقعی است.
