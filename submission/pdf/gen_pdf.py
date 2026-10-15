# -*- coding: utf-8 -*-
import arabic_reshaper
from bidi.algorithm import get_display
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_RIGHT, TA_CENTER, TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak
from reportlab.lib import colors

pdfmetrics.registerFont(TTFont('ArabType', 'C:/Windows/Fonts/arabtype.ttf'))
pdfmetrics.registerFont(TTFont('ArabTypeBd', 'C:/Windows/Fonts/arabtype.ttf'))

FONT = 'ArabType'
FONTB = 'ArabTypeBd'

def R(text):
    if not isinstance(text, str):
        text = str(text)
    return arabic_reshaper.reshape(text)

guide = open('F:/TELEGRAM AI ADMIN PROJECT/submission/GUIDE.md', encoding='utf-8').read()
lines = guide.split('\n')

styles = {
    'h1': ParagraphStyle('h1', fontName=FONTB, fontSize=20, leading=28, alignment=TA_CENTER, spaceAfter=12, textColor=colors.HexColor('#1a1a2e')),
    'h2': ParagraphStyle('h2', fontName=FONTB, fontSize=14, leading=20, alignment=TA_RIGHT, spaceBefore=14, spaceAfter=6, textColor=colors.HexColor('#16213e')),
    'h3': ParagraphStyle('h3', fontName=FONTB, fontSize=12, leading=17, alignment=TA_RIGHT, spaceBefore=8, spaceAfter=4, textColor=colors.HexColor('#0f3460')),
    'p': ParagraphStyle('p', fontName=FONT, fontSize=10, leading=15, alignment=TA_RIGHT, spaceAfter=6),
    'code': ParagraphStyle('code', fontName='Courier', fontSize=8.5, leading=12, alignment=TA_LEFT, backColor=colors.HexColor('#f4f4f4'), textColor=colors.HexColor('#333'), leftIndent=10, rightIndent=10, spaceAfter=8),
    'li': ParagraphStyle('li', fontName=FONT, fontSize=10, leading=15, alignment=TA_RIGHT, leftIndent=18, spaceAfter=3),
    'tbl': ParagraphStyle('tbl', fontName=FONT, fontSize=9, leading=12, alignment=TA_RIGHT),
}

def build_table(rows):
    data = []
    for r in rows:
        data.append([Paragraph(R(c), styles['tbl']) for c in r])
    ncols = len(data[0])
    t = Table(data, hAlign='RIGHT')
    style = [
        ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#16213e')),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
        ('ALIGN', (0, 0), (-1, -1), 'RIGHT'),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#ccc')),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f0f4f8')]),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ]
    t.setStyle(TableStyle(style))
    return t

elements = []
in_code = False
in_table = False
table_rows = []

for line in lines:
    s = line.strip()

    if s.startswith('```'):
        in_code = not in_code
        if not in_code and elements:
            pass
        continue

    if s.startswith('# '):
        if table_rows:
            elements.append(build_table(table_rows)); table_rows = []
            in_table = False
        elements.append(Paragraph(R(s[2:].strip()), styles['h1']))
        continue
    if s.startswith('## '):
        if table_rows:
            elements.append(build_table(table_rows)); table_rows = []; in_table = False
        elements.append(Paragraph(R(s[3:].strip()), styles['h2']))
        continue
    if s.startswith('### '):
        if table_rows:
            elements.append(build_table(table_rows)); table_rows = []; in_table = False
        elements.append(Paragraph(R(s[4:].strip()), styles['h3']))
        continue

    if s.startswith('|') and '|' in s[1:]:
        if not in_table:
            in_table = True
            table_rows = []
        cols = [c.strip() for c in s.strip('|').split('|')]
        table_rows.append(cols)
        continue
    else:
        if in_table and table_rows:
            elements.append(build_table(table_rows)); table_rows = []; in_table = False

    if in_code:
        elements.append(Paragraph(s, styles['code']))
        continue

    if s.startswith('1. ') or s.startswith('- ') or s.startswith('* '):
        txt = s[2:].strip()
        elements.append(Paragraph(R('• ' + txt), styles['li']))
        continue

    if s == '---':
        elements.append(Spacer(1, 6))
        continue

    if s == '':
        elements.append(Spacer(1, 4))
        continue

    elements.append(Paragraph(R(s), styles['p']))

if table_rows:
    elements.append(build_table(table_rows))

doc = SimpleDocTemplate(
    'F:/TELEGRAM AI ADMIN PROJECT/submission/pdf/enzo-guide-1.0.0.pdf',
    pagesize=A4,
    rightMargin=20*mm, leftMargin=20*mm,
    topMargin=20*mm, bottomMargin=20*mm,
)
doc.build(elements)
print('PDF built ok')