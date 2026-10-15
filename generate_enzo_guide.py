#!/usr/bin/env python3
"""
کامپیوتر راهنمای جامع فارسی برای پلاگین ENZO AI Telegram Admin
"""

from reportlab.lib.pagesizes import letter, A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.lib import colors
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, Image, PageBreak
from reportlab.lib.enums import TA_CENTER, TA_RIGHT, TA_LEFT, TA_JUSTIFY
from reportlab.pdfgen import canvas
from reportlab.lib.fonts import register
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import datetime
import os

# ثبت فونت فارسی برای پشتیبانی از متن راست به چپ (RTL)
# تلاش برای استفاده از فونت فارسی سیستم
farsi_font_path = "C:\\Windows\\Fonts\\Arial.ttf"  # بهتر است از فونت TrueType فارسی واقعی استفاده شود
# اگر فونت فارسی مخصوص وجود دارد، می‌توان آن را ثبت کرد
try:
    pdfmetrics.registerFont(TTFont('Vazir', farsi_font_path))
    PERSIAN_FONT = 'Vazir'
except:
    # از فونت پیش‌فرض سیستم استفاده کنید اگر فونت فارسی موجود نباشد
    PERSIAN_FONT = 'Helvetica'
    print("هشدار: فونت فارسی واقعی یافت نشد، از فونت پیش‌فرض استفاده می‌شود")

def create_enzo_guide_pdf():
    """ایجاد راهنمای PDF ENZO با استفاده از reportlab"""

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

    # عنوان اصلی با سبک فارسی
    title_style = ParagraphStyle(
        'CustomTitle',
        parent=styles['Heading1'],
        fontSize=24,
        spaceAfter=30,
        alignment=TA_CENTER,
        textColor=colors.darkblue,
        fontName=PERSIAN_FONT,
        leftIndent=0,
        rightIndent=0
    )

    subtitle_style = ParagraphStyle(
        'CustomSubtitle',
        parent=styles['Heading2'],
        fontSize=14,
        spaceAfter=20,
        alignment=TA_CENTER,
        textColor=colors.grey,
        fontName=PERSIAN_FONT,
        leftIndent=0,
        rightIndent=0
    )

    # استایل عنوان بخش با فلش RTL (راست به چپ)
    section_header_style = ParagraphStyle(
        'SectionHeader',
        fontSize=18,
        spaceAfter=15,
        alignment=TA_RIGHT,
        textColor=colors.darkblue,
        fontName=PERSIAN_FONT,
        leftIndent=20,
        rightIndent=20,
        borderColor=colors.lightblue,
        borderWidth=2,
        borderPadding=10,
        backgroundColor=colors.lightcyan,
        firstLineIndent=10
    )

    # استایل برای نکات (باید علائم TL (سمت چپ)) با توجه به RLT
    note_style = ParagraphStyle(
        'Note',
        fontSize=10,
        spaceAfter=10,
        alignment=TA_LEFT,
        textColor=colors.darkgreen,
        fontName=PERSIAN_FONT,
        leftIndent=30,
        rightIndent=30,
        bulletIndent=20,
        bulletChar='■'
    )

    # استایل برای جدول (مطمئن شوید که خوانایی RTL را در جدول‌ها حفظ می‌کند)
    table_style = ParagraphStyle(
        'TableText',
        fontSize=9,
        alignment=TA_RIGHT,
        fontName=PERSIAN_FONT
    )

    # بنر اصلی
    story.append(Paragraph("راهنمای جامع پلاگین ENZO AI Telegram Admin", title_style))
    story.append(Paragraph("نسخه: 1.0.0 | تاریخ: ۲۰۲۶-۱۰-۱۵", subtitle_style))
    story.append(Spacer(1, 20))

    # معرفی
    story.append(Paragraph("۱. مقدمه - آنچه ENZO است", section_header_style))
    story.append(Spacer(1, 10))

    # محتوای مقدمه - بر اساس توکن و README.md نوشته شده است
    intro_text = """
    <font name="%s" size="12">
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
    </font>
    """

    story.append(Paragraph(intro_text % PERSIAN_FONT, styles['Normal']))
    story.append(Spacer(1, 20))

    # پیش‌نیازها
    story.append(Paragraph("۲. پیش‌نیازهای نصب", section_header_style))
    story.append(Spacer(1, 10))

    # جدول پیش‌نیازها - بر اساس INSTALLATION.md
    requirements_data = [
        ['WordPress', '۶.۵+', 'تک‌سایتی (Multisite در نسخه ۱.۰ پشتیبانی نمی‌شود)'],
        ['PHP', '۸.۱+', 'با اکستنشن `sodium` (به‌صورت پیش‌فرض فعال است) و `json`'],
        ['دیتابیس', 'MySQL ۵.۷+ / MariaDB ۱۰.۳+', ''],
        ['کاربر وردپرس', 'نقش Administrator', 'همه بخش‌ها با `manage_options` محافظت می‌شوند'],
        ['Bot Token تلگرام یا بله', '—', 'از [@BotFather](https://t.me/BotFather) (تلگرام) یا [@BaleBot](https://t.me/BaleBot) (بله)'],
        ['کلید API هوش مصنوعی', '—', 'هر سرویس سازگار با OpenAI (OpenAI، OpenRouter، Groq، …)']
    ]

    requirements_table = Table(requirements_data, colWidths=[1.5*inch, 2*inch, 2.5*inch])
    requirements_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.lightblue),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.black),
        ('ALIGN', (0, 0), (-1, -1), TA_CENTER),
        ('ALIGN', (0, 1), (-1, -1), TA_RIGHT),
        ('FONTNAME', (0, 0), (-1, 0), PERSIAN_FONT),
        ('FONTNAME', (0, 1), (-1, -1), PERSIAN_FONT),
        ('FONTSIZE', (0, 0), (-1, 0), 12),
        ('FONTSIZE', (0, 1), (-1, -1), 10),
        ('BOTTOMPADDING', (0, 0), (-1, 0), 8),
        ('BOTTOMPADDING', (0, 1), (-1, -1), 6),
        ('GRID', 0, 0, 1, len(requirements_data), colors.grey, 1),
        ('WORDWRAP', 0, 0, -1, -1, True),
    ]))

    story.append(requirements_table)
    story.append(Spacer(1, 15))

    # نکات امنیتی
    security_note = Paragraph("""
    <font name="%s" size="10">
    <b>نکات امنیتی:</b> کلید رمزنگاری اختصاصی در wp-config.php تعریف کنید (توصیه‌شده):
    <br><br>
    define('ENZO_ENCRYPTION_KEY', 'یک-رشته-تصادفی-طولانی-حداقل-۳۲-کاراکتر');
    <br><br>
    بدون این تعریف، secretها با کلیدی مشتق شده از salts وردپرس رمز می‌شوند (کار می‌کند ولی کلید اختصاصی امن‌تر است).
    <br><br>
    ⚠️ این کلید را تغییر ندهید مگر آنکه بخواهید همه‌ی secretهای ذخیره شده دوباره وارد شوند؛ تغییر کلید = عدم امکان رمزگشایی مقادیر قبلی.
    </font>
    """ % PERSIAN_FONT)

    story.append(security_note)
    story.append(Spacer(1, 20))

    # مراحل نصب
    story.append(Paragraph("۳. مراحل نصب", section_header_style))
    story.append(Spacer(1, 10))

    installation_steps = """
    <font name="%s" size="12">
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

    ۴. <b>راه‌اندازی اولیه</b>:

    <b>۴.۱ انتخاب پلتفرم (تلگرام، بله، ایتا یا روبیکا)</b>:
    • منوی «ENZO: AI Admin ← پلتفرم» (یا «تنظیمات عمومی»)
    • انتخاب کنید **Telegram** (برنامه API: `https://api.telegram.org`)، **Bale** (`https://tapi.bale.ai`)، **It** (`https://api.it.ir`) یا **Rubika** (`https://api.robika.io`). ناحیه ایران با پلتفرم It پیش‌فرض می‌شود.
    • پلتفرم پلتفرم انتخاب شده برای همۀ پردازش‌های بعدی ثبت می‌شود (لیست ربات‌ها، دکمه‌های کنترل ادمین).

    <b>۴.۲ اتصال ربات</b> (برای هر سه پلتفرم):
    • منوی «ENZO: AI Admin ← ربات‌ها» را باز کنید. دکمه **+ ربات جدید** بزنید.
    • در لیست ربات‌های ثبت شده، روی پلتفرم موردنظر (Telegram، Bale، It یا Rubika) دوبار کلیک کنید یا «ویرایش» بزنید.
    • **Bot Token** را از:
      - تلگرام: [@BotFather](https://t.me/BotFather)
      - بله: [@BaleBot](https://t.me/BaleBot)
      - ایتا: [@ItBot](https://t.me/ItBot) (یا ربات رسمی ایتا)
      - روبیکا: [@RubikaBot](https://t.me/RubikaBot) (یا ربات رسمی روبیکا)
      کپی کرده و وارد کنید. افزونه با `getMe` اعتبارسنجی می‌کند؛ توکن نامعتبر ذخیره نمی‌شود.
    • اگر می‌خواهید ربات در یک کانال مدیریت شود، آن را به‌عنوان ادمین به کانال اضافه کنید (دسترسی «Post Messages» لازم است).

    <b>۴.۳ اتصال هوش مصنوعی</b>:
    • منوی «AI Providerها»
    • Name، Base URL (مثلاً `https://api.openai.com/v1/chat/completions`)، مدل و کلید API را وارد کنید.
    • «Test» بزنید — اتصال موفق باید «اتصال برقرار شد» بدهد.
    • Provider را پیش‌فرض کنید.

    کلیدها **رمزنگاری شده** ذخیره می‌شوند و هرگز در پاسخ APIها یا صفحه مدیریت برنمی‌گردند.

    <b>۴.۴ لایسنس</b>:
    از بخش «لایسنس» کلید خریداری شده از RTL-Theme را فعال کنید. فعال‌سازی دامنه سایت را ثبت می‌کند و به‌روزرسانی‌های امن را باز می‌کند.

    ۵. <b>پس از نصب</b>:

    • راهنمای استفاده روزمره: [USER-GUIDE.md](USER-GUIDE.md)
    • اگر چیزی کار نکرد: [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

    ۶. <b>حذف افزونه</b>:

    حذف افزونه از صفحه افزونه‌ها، **تمام** داده‌ها را پاک می‌کند: ۵ جدول، تنظیمات، توکن ربات و کلیدهای API. اگر می‌خواهید محتوا باقی بماند، قبل از حذف از «پست‌ها» خروجی بگیرید.
    </font>
    """

    story.append(Paragraph(installation_steps % PERSIAN_FONT, styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از اتصال به پلتفرم‌ها
    story.append(PageBreak())

    # اتصال به پلتفرم‌ها
    story.append(Paragraph("۴. اتصال به پلتفرم‌ها", section_header_style))
    story.append(Spacer(1, 10))

    platforms_text = """
    <font name="%s" size="12">
    <b>ENZO از چهار پلتفرم اصلی مطرح ایرانی پشتیبانی می‌کند:</b>

    <b>۱. تلگرام (Telegram)</b>
    • API: https://api.telegram.org
    • ربات‌های رسمی: [@BotFather](https://t.me/BotFather) برای ساخت، [@userinfobot](https://t.me/userinfobot) برای گرفتن شناسه کانال.
    • برای اتصال تلگرام:
      - Bot Token را از BotFather دریافت کنید.
      - ربات را به کانال (با شناسه منفی مثل `-1001234567890`) به عنوان ادمین اضافه کنید.
      - استفاده از شناسه منفی کانال برای انتشار.

    <b>۲. بله (Bale)</b>
    • API: https://tapi.bale.ai
    • ربات‌های رسمی: [@BaleBot](https://t.me/BaleBot) برای ساخت و مدیریت.
    • API مشابه تلگرام، ساختار داده‌ها یکسان است.

    <b>۳. ایتا (It)</b>
    • API: https://api.it.ir
    • ربات‌های رسمی: [@ItBot](https://t.me/ItBot) یا ربات رسمی ایتا.
    • پلتفرم جدید ایرانی با API مشابه تلگرام.

    <b>۴. روبیکا (Rubika)</b>
    • API: https://api.robika.io
    • ربات‌های رسمی: [@RubikaBot](https://t.me/RubikaBot) یا ربات رسمی روبیکا.
    • پلتفرم پیشرو ایرانی با API اختصاصی.

    <b>مراحل اتصال کلی برای هر پلتفرم:</b>
    ۱. در پیشخوان وردپرس، به بخش «ENZO: AI Admin ← پلتفرم» بروید.
    ۲. پلتفرم موردنظر را انتخاب کنید (Telegram، Bale، It یا Rubika).
    ۳. در بخش «روبات‌ها»، روی پلتفرم کلیک کنید و Bot Token را وارد کنید.
    ۴. ربات را به کانال با دسترسی «Post Messages» اضافه کنید.
    ۵. شناسه کانال (شناسه منفی) را ثبت کنید.

    <b>نکات امنیتی برای هر پلتفرم</b>
    • Bot Tokenها به صورت رمزنگاری شده ذخیره می‌شوند.
    • از getMe برای تأیید اعتبار توکن استفاده می‌شود.
    • هرگونه خطا در اعتبار‌سنجی ذخیره می‌شود و توکن نامعتبر ذخیره نمی‌شود.
    </font>
    """

    story.append(Paragraph(platforms_text % PERSIAN_FONT, styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از اتصال به هوش مصنوعی
    story.append(PageBreak())

    # اتصال به هوش مصنوعی
    story.append(Paragraph("۵. اتصال به هوش مصنوعی", section_header_style))
    story.append(Spacer(1, 10))

    ai_text = """
    <font name="%s" size="12">
    <b>مدیریت AI Providerها در ENZO</b>

    ENZO از هر سرویس سازگار با OpenAI (OpenAI، OpenRouter، Groq، Gemini و …) پشتیبانی می‌کند. هر Provider با Name، Base URL، مدل و کلید API مدیریت می‌شود.

    <b>مراحل اتصال AI:</b>

    ۱. <b>دسترسی به بخش AI Providerها</b>
    • در پیشخوان وردپرس: «ENZO: AI Admin ← AI Providerها» (یا «تنظیمات AI»)

    ۲. <b>افزودن Provider جدید</b>
    • کلیک روی دکمه **+ Provider جدید**
    • وارد کردن اطلاعات:
      - <b>Name:</b> مثلاً «OpenAI Default»
      - <b>Base URL:</b> `https://api.openai.com/v1/chat/completions`
      - <b>Model:</b> `gpt-4o` یا هر مدل دیگری
      - <b>API Key:</b> کلید API خود را وارد کنید

    ۳. <b>تأیید اتصال</b>
    • کلید **Test Connection** را بزنید
    • نتیجه: «اتصال برقرار شد» اگر موفق باشد، یا پیام خطا اگر ناموفق باشد
    • اگر اتصال موفق باشد، می‌توانید آن را به عنوان <b>Default Provider</b> تنظیم کنید

    ۴. <b>مدیریت Providerها</b>
    • ویرایش: روی دکمه ویرایش (سخن) کلیک کنید
    • حذف: روی دکمه حذف (مخفه) کلیک کنید و تأیید کنید
    • تغییر اولویت: از کلیدهایuparrow/down arrow برای تغییر ترتیب استفاده کنید
    • مشاهده آمار: هر Provider دارای آمار ارتباطات (موفق/ناموفق) است

    <b>نکات امنیتی برای کلید API</b>
    • کلیدهای API **رمزنگاری شده** ذخیره می‌شوند و هیچ‌وقت در صفحه یا لاگ‌ها نمایش داده نمی‌شوند.
    • از رمزنگاری AES-256 با کلید اختصاصی پلاگین استفاده می‌شود.
    • فقط شما (دارنده دسترسی مدیریت) می‌توانید کلیدها را مشاهده کنید.

    <b>پشتیبانی از چند Provider</b>
    • می‌توانید چندین Provider را با Base URLs مختلف ثبت کنید (مثلاً OpenAI برای تولید محتوا، Groq برای خلاصه‌سازی).
    • سیستم به صورت خودکار از اولویت ثبت‌شده استفاده می‌کند.
    • در تنظیمات، می‌توانید پیش‌فرض را برای هر نوع عملیات (تولید، بازنویسی، خلاصه) تغییر دهید.
    </font>
    """

    story.append(Paragraph(ai_text % PERSIAN_FONT, styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از بخش پست‌ها
    story.append(PageBreak())

    # پست‌ها
    story.append(Paragraph("۶. پست‌ها", section_header_style))
    story.append(Spacer(1, 10))

    posts_text = """
    <font name="%s" size="12">
    <b>مدیریت پست‌ها در ENZO</b>

    پس از تولید محتوا با AI، پست‌ها در سیستم مدیریت می‌شوند. هر پست دارای عملیات‌های زیر است:

    <b>۱. پیش‌نمایش و تأیید</b>
    • بعد از تولید یا وارد کردن محتوا، پیش‌نمایش را ببینید:
      - متن پست، کانال مقصد و تعداد کاراکترها
      - محدودیت‌های تلگرام: ۴۰۹۶ کاراکتر برای پیام متنی، ۱۰۲۴ برای کپشن عکس
    • اگر راضی هستید، «تأیید» بزنید
    • پست منتشرشده با علامت «pending» در جدول نمایش داده می‌شود

    <b>۲. زمان‌بندی انتشار</b>
    • با استفاده از تقویم و ساعت در بخش زمان‌بندی
    • فرمت: `YYYY-MM-DD HH:MM` (مثلاً `۲۰۲۶-۱۲-۰۱ ۱۴:۳۰`)
    • برای یک کار زمان‌بندی شده در صف، ایجاد می‌شود
    • تا ۱۰۰ کار در صف زمان‌بندی‌شده ذخیره می‌شود

    <b>۳. انتشار فوری</b>
    • برای انتشار فوری (همین حالا)
    • مناسب برای محتوای فوری یا لحظه‌ای
    • در صف ثبت می‌شود و سریع منتشر می‌شود

    <b>۴. ارسال عکس</b>
    • اگر به پست «تصویر شاخص» بدهید، پیام به صورت **عکس + کپشن در یک پیام** ارسال می‌شود
    • بدون تصویر، پیام متنی ارسال می‌شود
    • حجم تصویر باید کمتر از ۵ MB باشد (اعمال محدودیت پلاگین)

    <b>۵. تغییر وضعیت پست</b>
    • **pending**: پیش‌نویس، تأیید شده، در انتظار انتشار
    • **published**: منتشر شده (موفق)
    • **failed**: انتشار با خطا (قابل retry)
    • **cancelled**: لغو شده

    <b>۶. امکانات پست‌ها</b>
    • **پیگیری وضعیت**: هر پست دارای وضعیت و وضعیت آخر است
    • **retry خودکار**: صف انتشار به صورت اتمیک با retry و backoff نمایی (۲، ۴، ۸، … دقیقه) کار می‌کند
    • **Retry محدود**: حداکثر ۵ بار retry، بعد fail می‌شود
    • **retry دستی**: از بخش صف می‌توانید retry کنید
    • **لغو**: امکان لغو هر پست در صف وجود دارد

    <b>۷. تنظیمات پست‌ها</b>
    • لحن پیش‌فرض برای تولید محتوا
    • زبان پیش‌فرض برای تولید محتوا
    • قالب پست برای ساختار خروجی
    • پیش‌نمایش خودکار بعد از تولید
    • حداکثر تعداد کاراکترها برای پست‌های متنی

    <b>۸. گزارش‌گیری پست‌ها</b>
    • آمار کلی: تعداد پست‌های تولید شده، تأیید شده، منتشر شده، با خطا
    • آمار بر اساس کانال و پلتفرم
    • لاگ‌های کامل برای هر پست (با حفاظت از privacy)
    </font>
    """

    story.append(Paragraph(posts_text % PERSIAN_FONT, styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از بخش صف
    story.append(PageBreak())

    # صف
    story.append(Paragraph("۷. صف", section_header_style))
    story.append(Spacer(1, 10))

    queue_text = """
    <font name="%s" size="12">
    <b>صف انتشار اتمیک در ENZO</b>

    ENZO از یک سیستم صف اتمیک برای انتشار پست‌ها استفاده می‌کند. صف برای اطمینان از انتشار یکبار مصرف و اتمیک طراحی شده است.

    <b>ویژگی‌های صف</b>

    • **عملیات اتمیک**: کارها به صورت اتمیک claim می‌شوند (قفل با TTL ۵ دقیقه) — با چند درخواست cron هم انتشار تکراری رخ نمی‌دهد.
    • **حد کار در هر گذر cron**: حداکثر **۵ کار** در هر گذر cron اجرا می‌شود (کنترل بار سرور).
    • **retry نمایی**: شکست‌ها تا **۵ بار** با فاصله نمایی تکرار می‌شوند (۲، ۴، ۸، … دقیقه، سقف ۱ ساعت) و بعد با دلیل ثبت‌شده fail می‌شوند.
    • **امکانات**: اجرای فوری («Run now»)، لغو، retry دستی.

    <b>نحوه عملکرد صف</b>

    ۱. <b>ثبت کار</b>
    • بعد از تأیید یک پست، یک کار در صف ثبت می‌شود با:
      - شناسه پست (کلید اصلی)
      - زمان زمان‌بندی (برای پست‌های زمان‌بندی شده)
      - شناسه کانال
      - داده‌های پست (رمزنگاری شده)

    ۲. <b>Claim اتمیک</b>
    • کرون جاب هر ۵ دقیقه اجرا می‌شود
    • هر بار حداکثر ۵ کار را claim می‌کند
    • برای هر کار، قفل ایجاد می‌شود تا کار دیگری نتواند claim کند
    • قفل ۵ دقیقه پس از claim منقضی می‌شود (fail در صورت timeout)

    ۳. <b>اجرای کار</b>
    • کار منتشر می‌شود به Bot پلتفرم
    • وضعیت به صورت خودکار به روز می‌شود
    • error در صورت وجود ذخیره می‌شود
    • کار فقط تا زمانی که قفل فعال است قابل retry است

    ۴. <b>Retry و خطا</b>
    • بعد از شکست، کار به صورت خودکار retry می‌شود
    • الگوریتم retry: ۲، ۴، ۸، ۱۶، ۳۲، ۶۴ دقیقه (حداکثر ۱ ساعت)
    • بعد از ۵ بار شکست، کار fail می‌شود و دلیل آن ثبت می‌شود
    • کار fail‌شده هنوز در صف باقی می‌ماند (قابل retry دستی)

    <b>مدیریت صف</b>

    • **اجرای فوری**: «Run now» برای پست‌های معلق
    • **لغو**: لغو کار در صف (فقط کارهایی که هنوز اجرا نشده‌اند)
    • **retry دستی**: retry فوری بدون انتظار الگوریتم retry
    • **مشاهده جزئیات**: هر کار دارای شناسه، وضعیت، آخرین خطا و timestamp است

    <b>محدودیت‌های صف</b>

    • **حد کار**: ۵ کار در هر گذر cron (قابل تنظیم)
    • **Retry محدود**: حداکثر ۵ بار retry، بعد fail می‌شود
    • **حد زمانی**: کارهای Claim‌شده به مدت ۵ دقیقه قفل می‌شوند
    • **حجم صف**: تا ۱۰۰ کار در صف زمان‌بندی شده

    <b>خطاهای رایج صف</b>

    • **قفل منقضی**: کار Claim‌شده قابل retry نیست (تکرار خودکار)
    • **خطای API**: خطا در ارتباط با Bot پلتفرم
    • **خطای تایم‌اوت**: Bot پاسخ نمی‌دهد (retry)
    • **خطای محدودیت نرخ**: از محدودیت نرخ Bot پلتفرم (waiting)
    • **خطای Auth**: توکن منقضی یا نامعتبر (نیاز به اعتبارسنجی مجدد)

    <b>پیگیری وضعیت صف</b>

    • **داشبورد**: آمار کلی صف (کل کارها، کارهای معلق، کارهای موفق، کارهای با خطا)
    • **لیست کارها**: مشاهده وضعیت هر کار به صورت جزئی
    • **فیلتر**: بر اساس وضعیت، پلتفرم، کانال
    • **خروجی**: خروجی کارها برای تحلیل (CSV, JSON)
    </font>
    """

    story.append(Paragraph(queue_text % PERSIAN_FONT, styles['Normal']))
    story.append(Spacer(1, 20))

    # صفحه بریک قبل از بخش پشتیبانی
    story.append(PageBreak())

    # پشتیبانی
    story.append(Paragraph("۸. پشتیبانی", section_header_style))
    story.append(Spacer(1, 10))

    support_text = """
    <font name="%s" size="12">
    <b>پشتیبانی و راهنمایی در ENZO</b>

    ENZO دارای سیستم جامع پشتیبانی است که شامل مستندات، راهنمایی‌ها و امکان ارتباط با تیم پشتیبانی می‌شود.

    <b>۱. مستندات درون برنامه‌ای</b>

    • **راهنمای نصب**: [INSTALLATION.md](INSTALLATION.md) — راهنمای گام به گام نصب
    • **راهنمای استفاده**: [USER-GUIDE.md](USER-GUIDE.md) — راهنمای استفاده روزمره
    • **پرسش‌های متداول**: [FAQ.md](FAQ.md) — پرسش‌های متداول و پاسخ‌ها
    • **عیب‌یابی**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md) — راهنمای عیب‌یابی
    • **هسته فنی**: [ARCHITECTURE.md](ARCHITECTURE.md) — معماری فنی برای توسعه‌دهندگان
    • **تاریخچه نسخه‌ها**: [CHANGELOG.md](CHANGELOG.md) — تغییرات در نسخه‌ها
    • **امنیت**: [SECURITY.md](SECURITY.md) — امنیت و گزارش آسیب‌پذیری‌ها

    <b>۲. پشتیبانی درون برنامه‌ای</b>

    • **گزارش مشکل**: از بخش «ENZO: AI Admin ← تنظیمات» گزینه «گزارش مشکل» را انتخاب کنید
    • اطلاعات مورد نیاز: شناسه نسخه، وضعیت، جزئیات خطا، لاگ‌های سیستم
    • تیم پشتیبانی به صورت خودکار شکایت را بررسی و تیکت ایجاد می‌کند

    <b>۳. ارتباط با ما</b>

    • **ریپو اصلی**: [GitHub](https://github.com/RTL-Theme/enzo-telegram-ai-admin)
    • **وب‌سایت**: https://rtl-theme.com/plugins/enzo
    • **رها کردن مشکلات**: [GitHub Issues](https://github.com/RTL-Theme/enzo-telegram-ai-admin/issues)
    • **دردشة**: [@RTL_Theme](https://t.me/RTL_Theme) در تلگرام

    <b>۴. راهنمایی‌های مفید</b>

    • **در شروع به کار**: اگر تمام تنظیمات انجام شده ولی Bot متصل نمی‌شود، از [%username@userinfobot](https://t.me/userinfobot) برای گرفتن شناسه کانال استفاده کنید
    • **خروجی داده‌ها**: اگر می‌خواهید محتوای پست‌های خود را وارد کنید، از بخش «پست‌ها» روی دکمه «خروجی» کلیک کنید و فایل CSV را دانلود کنید
    • **تنظیمات پیشرفته**: برای تنظیمات پیشرفته، فایل wp-config.php را ویرایش کنید و ثابت ENZO_ENCRYPTION_KEY را تنظیم کنید
    • **به‌روزرسانی‌ها**: همیشه نسخه آخر پلاگین را دانلود کنید و از صفحه افزونه‌ها آن را نصب کنید (به‌روزرسانی خودکار فعال نیست)

    <b>۵. فراخوانی جامعه</b>

    • **اختلالات**: اگر با مشکل مواجه شدید، از بخش «پست‌ها» گزینه «گزارش مشکل» را بزنید
    • **پیشنهادات**: پیشنهادات خود را در کانال تلگرام RTL-Theme با #enzo-feedback به اشتراک بگذارید
    • **داخله**: به صورت داوطلب در بهبود مستندات کمک کنید و پلاگین را به زبان‌های دیگر ترجمه کنید

    <b>۶. اطلاعات نسخه و محدوده‌ها</b>

    • **نسخه فعلی**: ۱.۰.۰ (تاریخ: ۲۰۲۶-۱۰-۱۵)
    • **محدوده‌های نسخه ۱.۰**:
      - تک‌ربات، تک‌سایت (بدون Multisite)
      - پیام متنی + عکس (بدون آلبوم/ویدیو)
      - قالب پرامپت سفارشی در نسخه بعدی
      - رابط مدیریت فعلی: داشبورد و اتصال تلگرام به صورت کامل تعاملی؛ بخش‌های دیگر از طریق REST API (توسعه رابط در ادامه)

    <b>۷. اطلاعات تماس اضطراری</b>

    • در صورت مشکل فوری: فوراً شکایت را گزارش کنید و با تیم پشتیبانی تماس بگیرید
    • در صورت خرابی امنیتی: بلافاصله با RTL-Theme تماس بگیرید و گزارش آسیب‌پذیری را ارسال کنید
    • در صورت نیاز به راهنمایی: از وب‌سایت پشتیبانی استفاده کنید یا به کانال تلگرام ما بپیوندید

    <b>۸. حریم خصوصی و داده‌ها</b>

    • داده‌های شما محفوظ می‌مانند و طبق سیاست حریم خصوصی RTL-Theme مدیریت می‌شوند
    • کلیدهای API و Bot Tokenها رمزنگاری شده ذخیره می‌شوند
    • لاگ‌ها به صورت خودکار redaction می‌شوند (حذف sensitiveها)
    • retention لاگ‌ها: ۳۰ روز (قابل تنظیم)

    <b>۹. لیست بررسی شروع به کار</b>

    ☐ پلاگین نصب و فعال شده است
    ☐ کلید رمزنگاری در wp-config.php تنظیم شده است (توصیه‌شده)
    ☐ پلتفرم موردنظر (Telegram, Bale, It, Rubika) انتخاب شده است
    ☐ Bot Token از ربات رسمی دریافت شده است
    ☐ ربات به کانال با دسترسی Post Messages اضافه شده است
    ☐ شناسه کانال ثبت شده است
    ☐ حداقل یک Provider AI اضافه شده و مورد آزمایش قرار گرفته است
    ☐ لایسنس فعال شده است (اختیاری برای استفاده محدود)
    ☐ محیط تست: از سایت تستی استفاده کنید اگر موجود باشد
    ☐ phpinfo را در دسترس داشته باشید (برای عیب‌یابی)
    ☐ حداکثر ۵٪ از سهمیه API استفاده شده است (برای مدیریت سهمیه)

    با پایان راهنمای جامع فارسی! 🎉
    </font>
    """

    story.append(Paragraph(support_text % PERSIAN_FONT, styles['Normal']))
    story.append(Spacer(1, 30))

    # پانوشت
    footer_style = ParagraphStyle(
        'Footer',
        fontSize=8,
        alignment=TA_CENTER,
        textColor=colors.grey,
        fontName=PERSIAN_FONT
    )

    current_date = datetime.datetime.now().strftime('%Y-%m-%d')
    footer_text = """
    <font name="%s" size="8">
    راهنمای ENZO AI Telegram Admin — نسخه 1.0.0 | ایجاد شده در %s | پشتیبانی کامل از پلتفرم‌های تلگرام، بله، ایتا و روبیکا | توسعه توسط RTL-Theme
    </font>
    """ % (PERSIAN_FONT, current_date)

    story.append(Paragraph(footer_text % PERSIAN_FONT, footer_style))

    # ساخت PDF
    doc.build(story)

    print(f"PDF با موفقیت در {pdf_path} ایجاد شد!")

if __name__ == "__main__":
    create_enzo_guide_pdf()
    print("عملیات تکمیل شد.")
"""