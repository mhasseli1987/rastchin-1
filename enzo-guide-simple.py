#!/usr/bin/env python3
"""
نسخه ساده راهنمای PDF ENZO AI Telegram Admin
"""

from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.lib import colors
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, PageBreak
from reportlab.lib.enums import TA_CENTER, TA_RIGHT
from reportlab.pdfgen import canvas
import datetime
import os

def create_enzo_guide_pdf():
    """ایجاد راهنمای PDF ENZO به زبان فارسی"""

    # ایجاد دایرکتوری submission/pdf اگر وجود ندارد
    output_dir = "F:\\TELEGRAM AI ADMIN PROJECT\\submission\\pdf"
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    # مسیر خروجی PDF
    pdf_path = os.path.join(output_dir, "enzo-guide-1.0.0.pdf")

    # ایجاد سند PDF
    doc = SimpleDocTemplate(
        pdf_path,
        pagesize=A4,
        rightMargin=0.5*inch,
        leftMargin=0.5*inch,
        topMargin=0.5*inch,
        bottomMargin=0.5*inch
    )

    # لیست بلوک‌های محتوا
    story = []

    # استایل‌های سفارشی برای PDF
    styles = getSampleStyleSheet()

    # عنوان اصلی
    title_style = ParagraphStyle(
        'CustomTitle',
        fontSize=24,
        spaceAfter=30,
        alignment=TA_CENTER,
        textColor=colors.darkblue,
        fontName='Helvetica',
    )

    # استایل عنوان بخش
    section_header_style = ParagraphStyle(
        'SectionHeader',
        fontSize=18,
        spaceAfter=15,
        alignment=TA_RIGHT,
        textColor=colors.darkblue,
        fontName='Helvetica',
        leftIndent=20,
        rightIndent=20,
        borderColor=colors.lightblue,
        borderWidth=2,
        borderPadding=10,
        backgroundColor=colors.lightcyan,
    )

    # بنر اصلی
    story.append(Paragraph("راهنمای جامع پلاگین ENZO AI Telegram Admin", title_style))
    story.append(Paragraph("نسخه: 1.0.0 | تاریخ: ۲۰۲۶-۱۰-۱۵", styles['Heading2']))
    story.append(Spacer(1, 20))

    # مقدمه
    story.append(Paragraph("۱. مقدمه - آنچه ENZO است", section_header_style))
    story.append(Spacer(1, 10))

    intro_text = """
    <b>ENZO: پلتفرم هوشمند مدیریت و تولید محتوای تلگرام و بله</b> (پارسی: <i>انزو</i>) یک افزونه وردپرس قدرتمند است که به شما امکان می‌دهد کانال‌های تلگرام، بله، ایتا و روبیکا را به صورت هوشمند مدیریت کنید.

    <b>ویژگی‌های اصلی:</b>
    • اتصال Bot Token تلگرام، بله، ایتا یا روبیکا با اعتبارسنجی getMe و ذخیره‌سازی رمزنگاری شده
    • اتصال به هر سرویس سازگار با OpenAI (OpenAI، OpenRouter، Groq و …) با مدیریت چند Provider
    • تولید / بازنویسی / خلاصه / تیتر / کپشن / ترجمه / سئو / استخراج هشتگ محتوا با ذخیره‌سازی خودکار پیش‌نویس
    • پیش‌نمایش، تأیید، زمان‌بندی و انتشار فوری به کانال
    • صف انتشار اتمیک با retry و backoff نمایی (حداکثر ۵ کار در هر دقیقه)
    • پشتیبانی از ارسال عکس + کپشن در یک پیام
    • آلبوم عکس (Media Group): ارسال چندین عکس به صورت اسلاید در یک پیام
    • پشتیبانی از پلتفرم‌های داخلی ایران: بله (Bale)، ایتا (It)، روبیکا (Rubika)
    • بهینه‌سازی SEO برای محتوای تولید شده
    • استخراج هشتگ هوشمند برای کلمات کلیدی
    • داشبورد، لاگ‌های ساختاریافته با retention و redaction خودکار secretها
    • سیستم لایسنس fail-open با اتصال دامنه (۵ بار تغییر دامنه) و سیستم به‌روزرسانی امن از سرور RTL-Theme

    این افزونه کاملاً به زبان فارسی نوشته شده است و رابط کاربری بین‌راسی را برای کاربران فارسی‌زبان ارائه می‌دهد.
    """

    story.append(Paragraph(intro_text, styles['Normal']))
    story.append(Spacer(1, 20))

    # پیش‌نیازها
    story.append(Paragraph("۲. پیش‌نیازهای نصب", section_header_style))
    story.append(Spacer(1, 10))

    requirements_data = [
        ['WordPress', '۶.۵+', 'تک‌سایتی (Multisite در نسخه ۱.۰ پشتیبانی نمی‌شود)'],
        ['PHP', '۸.۱+', 'با اکستنشن `sodium` (به‌صورت پیش‌فرض فعال است) و `json`'],
        ['دیتابیس', 'MySQL ۵.۷+ / MariaDB ۱۰.۳+', ''],
        ['کاربر وردپرس', 'نقش Administrator', 'همه بخش‌ها با `manage_options` محافظت می‌شوند'],
        ['Bot Token تلگرام یا بله', '—', 'از [@BotFather](https://t.me/BotFather) (تلگرام) یا [@BaleBot](https://t.me/BaleBot) (بله)'],
        ['کلید API هوش مصنوعی', '—', 'هر سرویس سازگار با OpenAI (OpenAI، OpenRouter، Groq، …)']
    ]

    requirements_table = Table(requirements_data, colWidths=[1.5*inch, 2*inch, 2.5*inch])
    requirements_table.setStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.lightblue),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.black),
        ('ALIGN', (0, 0), (-1, -1), TA_CENTER),
        ('ALIGN', (0, 1), (-1, -1), TA_RIGHT),
        ('FONTSIZE', (0, 0), (-1, 0), 12),
        ('FONTSIZE', (0, 1), (-1, -1), 10),
        ('BOTTOMPADDING', (0, 0), (-1, 0), 8),
        ('BOTTOMPADDING', (0, 1), (-1, -1), 6),
        ('GRID', 0, 0, 1, len(requirements_data), colors.grey, 1),
        ('WORDWRAP', 0, 0, -1, -1, True),
    ])

    story.append(requirements_table)
    story.append(Spacer(1, 15))

    # نکات امنیتی
    security_note = Paragraph("""
    <b>نکات امنیتی:</b> کلید رمزنگاری اختصاصی در wp-config.php تعریف کنید (توصیه‌شده):
    <br><br>
    define('ENZO_ENCRYPTION_KEY', 'یک-رشته-تصادفی-طولانی-حداقل-۳۲-کاراکتر');
    <br><br>
    بدون این تعریف، secretها با کلیدی مشتق شده از salts وردپرس رمز می‌شوند (کار می‌کند ولی کلید اختصاصی امن‌تر است).
    <br><br>
    ⚠️ این کلید را تغییر ندهید مگر آنکه بخواهید همه‌ی secretهای ذخیره شده دوباره وارد شوند؛ تغییر کلید = عدم امکان رمزگشایی مقادیر قبلی.
    """)

    story.append(security_note)
    story.append(Spacer(1, 20))

    # مراحل نصب (خلاصه)
    story.append(Paragraph("۳. مراحل نصب", section_header_style))
    story.append(Spacer(1, 10))

    story.append(Paragraph("""
    <b>مراحل نصب افزونه ENZO:</b>

    ۱. <b>دانلود فایل zip</b>: از حساب کاربری RTL-Theme فایل zip را دانلود کنید.

    ۲. <b>نصب افزونه</b>:
       • از پیشخوان وردپرس: افزونه‌ها ← افزودن ← «بارگذاری افزونه» ← فایل zip ← نصب ← فعال‌سازی.
       • به صورت دستی: پوشه `enzo-telegram-ai-admin` را در `wp-content/plugins/` قرار دهید و از صفحه افزونه‌ها فعال کنید.

    فعال‌سازی خودکار ۵ جدول دیتابیس (`enzo_posts`, `enzo_channels`, `enzo_providers`, `enzo_queue`, `enzo_logs`) و زمان‌بندی cron صف انتشار را انجام می‌دهد.

    ۳. <b>تنظیم کلید رمزنگاری</b> (پیشنهادی):

    بدون این تعریف، secretها با کلیدی مشتق شده از salts وردپرس رمز می‌شوند (کار می‌کند ولی کلید اختصاصی امن‌تر است):

    ```php
    // wp-config.php — قبل از خط /* That's all, stop editing! */
    define('ENZO_ENCRYPTION_KEY', 'یک-رشته-تصادفی-طولانی-حداقل-۳۲-کاراکتر');
    ```

    ⚠️ این کلید را تغییر ندهید مگر آنکه بخواهید همه‌ی secretهای ذخیره شده دوباره وارد شوند؛ تغییر کلید = عدم امکان رمزگشایی مقادیر قبلی.

    ۴. <b>راه‌اندازی اولیه</b>: انتخاب پلتفرم، اتصال ربات، اتصال AI و تنظیم لایسنس.
    """"", styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از اتصال به پلتفرم‌ها
    story.append(PageBreak())

    # اتصال به پلتفرم‌ها
    story.append(Paragraph("۴. اتصال به پلتفرم‌ها", section_header_style))
    story.append(Spacer(1, 10))

    platforms_text = """
    <b>پلتفرم‌های پشتیبانی‌شده</b>

    • تلگرام (Telegram) – API: https://api.telegram.org
    • بله (Bale) – API: https://tapi.bale.ai
    • ایتا (It) – API: https://api.it.ir
    • روبیکا (Rubika) – API: https://api.robika.io

    هر پلتفرم دارای ربات‌های رسمی و شناسه‌های منفی برای کانال‌هاست.
    """

    story.append(Paragraph(platforms_text, styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از اتصال به هوش مصنوعی
    story.append(PageBreak())

    # اتصال به هوش مصنوعی
    story.append(Paragraph("۵. اتصال به هوش مصنوعی", section_header_style))
    story.append(Spacer(1, 10))

    story.append(Paragraph("""
    <b>مدیریت AI Providerها</b>

    ENZO از هر سرویس سازگار با OpenAI (OpenAI، OpenRouter، Groq، Gemini و …) پشتیبانی می‌کند. هر Provider با Name، Base URL، مدل و کلید API مدیریت می‌شود.

    <b>مراحل اتصال AI</b>:

    ۱. در پیشخوان وردپرس: «ENZO: AI Admin ← AI Providerها»
    ۲. کلیک روی **+ Provider جدید** و وارد کردن Name، Base URL، مدل و API Key
    ۳. کلید **Test Connection** را بزنید و نتیجه را مشاهده کنید
    ۴. Provider را به عنوان Default Provider تنظیم کنید

    کلیدها به صورت رمزنگاری شده ذخیره می‌شوند و هیچ‌وقت نمایش داده نمی‌شوند.
    """"", styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از بخش پست‌ها
    story.append(PageBreak())

    # پست‌ها
    story.append(Paragraph("۶. پست‌ها", section_header_style))
    story.append(Spacer(1, 10))

    story.append(Paragraph("""
    <b>مدیریت پست‌ها</b>

    بعد از تولید محتوا با AI، پست‌ها در سیستم مدیریت می‌شوند. هر پست دارای عملیات‌های پیش‌نمایش، تأیید، زمان‌بندی یا انتشار فوری است.

    <b>ویژگی‌های پست‌ها</b>

    • پیش‌نمایش متن، کانال مقصد و تعداد کاراکترها
    • تأیید برای انتشار
    • زمان‌بندی با فرمت YYYY-MM-DD HH:MM
    • انتشار فوری برای ارسال هم‌اکنون
    • ارسال عکس همراه با کپشن در یک پیام
    • وضعیت‌ها: pending (پیش‌نویس)، published (منتشر شده)، failed (با خطا)، cancelled (لغو شده)
    """"", styles['Normal']))
    story.append(Spacer(1, 20))

    # صف
    story.append(Paragraph("۷. صف", section_header_style))
    story.append(Spacer(1, 10))

    story.append(Paragraph("""
    <b>صف انتشار اتمیک</b>

    صف برای اطمینان از انتشار یکبار مصرف و اتمیک طراحی شده است.

    <b>ویژگی‌های صف</b>

    • کارها به صورت اتمیک claim می‌شوند (قفل با TTL ۵ دقیقه)
    • حداکثر ۵ کار در هر گذر cron اجرا می‌شود
    • retry نمایی: ۲، ۴، ۸، … دقیقه (حداکثر ۱ ساعت)
    • امکانات: اجرای فوری، لغو، retry دستی
    """"", styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از بخش پشتیبانی
    story.append(PageBreak())

    # پشتیبانی
    story.append(Paragraph("۸. پشتیبانی", section_header_style))
    story.append(Spacer(1, 10))

    story.append(Paragraph("""
    <b>پشتیبانی و مستندات</b>

    • مستندات درون برنامه‌ای: INSTALLATION.md، USER-GUIDE.md، FAQ.md، TROUBLESHOOTING.md
    • گزارش مشکل از طریق بخش «گزارش مشکل» در پلاگین
    • ارتباط با ما: GitHub، تلگرام @RTL_Theme
    • لیست بررسی شروع به کار برای تنظیمات پایه
    • پشتیبانی فارسی‌زبان با محتوای کامل فارسی
    """"", styles['Normal']))
    story.append(Spacer(1, 30))

    # پانوشت
    current_date = datetime.datetime.now().strftime('%Y-%m-%d')
    footer_text = f"راهنمای ENZO AI Telegram Admin — نسخه 1.0.0 | ایجاد شده در {current_date} | پشتیبانی کامل از پلتفرم‌های تلگرام، بله، ایتا و روبیکا | توسعه توسط RTL-Theme"

    footer_style = ParagraphStyle(
        'Footer',
        fontSize=8,
        alignment=TA_CENTER,
        textColor=colors.grey,
    )

    story.append(Paragraph(footer_text, footer_style))

    # ساخت PDF
    doc.build(story)

    print(f"PDF با موفقیت در {pdf_path} ایجاد شد!")

if __name__ == "__main__":
    create_enzo_guide_pdf()
    print("عملیات تکمیل شد.")
