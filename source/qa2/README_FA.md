> **QA2 STAGING CANDIDATE — NOT APPROVED FOR PRODUCTION**: Health check requires financial_commit_keys and schema marker rc7-qa1. No actual MariaDB acceptance yet.

# سکناهاوس — کاندید آزمایشی 2.6.19-dev.25-qa2

**فقط برای Staging و QA. روی داده واقعی یا هاست عملیاتی نصب یا Update نشود.**

این خروجی از سورس تجمیعی QA دور ۲۲ ساخته شده است؛ پایگاه داده در مقایسه با نسخه `2.6.19-dev.24` جدول جدید `financial_commit_keys` دارد (`2.6.0-rc7-qa1`). اصلاحات رابط کاربری، مالی، Backup، Restore و Update در آن تجمیع شده‌اند، ولی نتیجه کامل روی MariaDB و هاست واقعی تأیید نشده است.

- فایل نصب تمیز برای سایت و دیتابیس **آزمایشی خالی** است.
- فایل Update منتشر نشده است، چون جدول جدید مالی به Migration واقعی نیاز دارد.
- پیش‌نیازها: PHP 8.1+، `pdo_mysql`، `mbstring`، `zip`، `json`، MariaDB/MySQL با InnoDB و `utf8mb4`.
- پیش از Update باید از فایل‌ها و DB آزمایشی نسخه پشتیبان مستقل تهیه و صحت آن بررسی شود.
- بازگشت خودکار پس از Update موفق عمداً مسدود است تا داده تازه با Snapshot قدیمی جایگزین نشود.
- معیار پذیرش: نصب تمیز، تراکنش/رزرو/پرداخت واقعی آزمایشی، Backup/Restore/Update و UAT مرورگرها.

راهنمای کامل گیت‌های آزمون در `docs/DEV_2_6_19_DEV25_QA_HANDOVER_FA.md` و خارج از بسته در گزارش ممیزی قرار دارد.
