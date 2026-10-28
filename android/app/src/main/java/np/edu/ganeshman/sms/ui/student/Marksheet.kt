package np.edu.ganeshman.sms.ui.student

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.print.PrintAttributes
import android.print.PrintManager
import android.util.Base64
import android.webkit.WebView
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Print
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import np.edu.ganeshman.sms.R
import np.edu.ganeshman.sms.data.ResultDetail
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.common.prettyDate
import np.edu.ganeshman.sms.ui.components.AsyncContent
import np.edu.ganeshman.sms.ui.components.rememberAsync
import np.edu.ganeshman.sms.ui.theme.*
import java.time.LocalDate
import kotlin.math.floor

private fun grade(obt: Double, full: Double): String {
    if (full <= 0) return "NG"
    val p = obt / full * 100
    return when {
        p >= 90 -> "A+"; p >= 80 -> "A"; p >= 70 -> "B+"; p >= 60 -> "B"
        p >= 50 -> "C+"; p >= 40 -> "C"; p >= 35 -> "D"; else -> "NG"
    }
}

private fun esc(s: String) = s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")

private fun logoDataUri(ctx: Context): String = try {
    val bmp = BitmapFactory.decodeResource(ctx.resources, R.drawable.ic_logo)
    val small = Bitmap.createScaledBitmap(bmp, 160, 160, true)
    val out = java.io.ByteArrayOutputStream()
    small.compress(Bitmap.CompressFormat.PNG, 100, out)
    "data:image/png;base64," + Base64.encodeToString(out.toByteArray(), Base64.NO_WRAP)
} catch (e: Exception) { "" }

/** Same layout as the school website's printable result sheet (admin Marks & Results -> View). */
fun marksheetHtml(
    ctx: Context, studentName: String, studentCode: String, className: String, r: ResultDetail,
): String {
    var totalObt = 0.0; var totalFull = 0.0; var anyFailed = false
    val rows = StringBuilder()
    r.subjects.forEach { s ->
        val full = s.fullMarks.toDouble(); val pass = s.passMarks.toDouble(); val obt = s.marksObtained
        val passed = obt >= pass
        if (!passed) anyFailed = true
        totalObt += obt; totalFull += full
        val pct = if (full > 0) "%.1f%%".format(obt / full * 100) else "–"
        rows.append(
            "<tr><td>${esc(s.subjectName)}</td><td class=c>${s.fullMarks}</td><td class=c>${s.passMarks}</td>" +
                "<td class=c style=\"font-weight:700;color:${if (passed) "#166534" else "#991b1b"}\">${floor(obt).toInt()}</td>" +
                "<td class=c>$pct</td>" +
                "<td class=c style=\"font-weight:700;color:${if (!passed) "#991b1b" else "#1a3c5e"}\">${grade(obt, full)}</td>" +
                "<td class=c style=\"font-weight:700;color:${if (passed) "#166534" else "#991b1b"}\">${if (passed) "Pass" else "NG"}</td></tr>"
        )
    }
    val ovGrade = grade(totalObt, totalFull)
    val ovPassed = !anyFailed && ovGrade != "NG"
    val ovPct = if (totalFull > 0) "%.1f%%".format(totalObt / totalFull * 100) else "–"
    val ovColor = if (ovPassed) "#166534" else "#991b1b"
    val tot = "${floor(totalObt).toInt()} / ${floor(totalFull).toInt()}"
    val logo = logoDataUri(ctx)
    val examDate = prettyDate(r.startDate)
    val printed = prettyDate(LocalDate.now().toString())
    val exam = esc(r.examName) + if (r.examType.isNotBlank()) " (${esc(r.examType)})" else ""

    return """<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=794, initial-scale=1">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Times New Roman',serif;background:#fff;color:#1a1a1a;}
.sheet{width:210mm;min-height:297mm;margin:0 auto;padding:16mm 14mm;}
.c{text-align:center}
.header{display:flex;align-items:center;gap:18px;border-bottom:3px double #1a3c5e;padding-bottom:12px;margin-bottom:6px;}
.logo{width:70px;height:70px;object-fit:contain;}
.school-info{flex:1;text-align:center;}
.school-name{font-size:21pt;font-weight:700;color:#1a3c5e;letter-spacing:1px;line-height:1.15;}
.school-sub{font-size:9.5pt;color:#444;margin-top:3px;}
.school-tag{font-size:8.5pt;color:#666;margin-top:2px;}
.doc-type{text-align:center;margin:10px 0 14px;}
.doc-type h2{font-size:13pt;font-weight:700;color:#1a3c5e;letter-spacing:2px;text-transform:uppercase;border:2px solid #1a3c5e;display:inline-block;padding:4px 26px;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:14px;font-size:10pt;}
.info-row{display:flex;gap:6px;}
.info-label{font-weight:700;min-width:110px;}
.info-val{border-bottom:1px dotted #999;flex:1;padding-bottom:1px;}
table{width:100%;border-collapse:collapse;font-size:10pt;margin-bottom:14px;}
thead tr{background:#1a3c5e;color:#fff;}
thead th{padding:7px 9px;text-align:center;font-weight:700;border:1px solid #1a3c5e;font-size:9pt;}
thead th:first-child{text-align:left;}
tbody tr:nth-child(even){background:#f8f9fa;}
tbody td{padding:6px 9px;border:1px solid #ccc;}
tfoot tr{background:#e8edf5;font-weight:700;}
tfoot td{padding:7px 9px;border:1px solid #aaa;font-size:10.5pt;}
.summary-box{border:2px solid #1a3c5e;border-radius:4px;padding:10px 16px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
.sum-item{text-align:center;}
.sum-label{font-size:8pt;color:#555;text-transform:uppercase;letter-spacing:.5px;}
.sum-val{font-size:14pt;font-weight:700;color:#1a3c5e;}
.result-stamp{font-size:18pt;font-weight:900;padding:5px 18px;border:3px solid;border-radius:6px;}
.result-pass{color:#166534;border-color:#166534;}
.result-fail{color:#991b1b;border-color:#991b1b;}
.grade-scale{font-size:8pt;color:#555;border:1px solid #ddd;padding:5px 9px;border-radius:3px;margin-bottom:14px;}
.grade-scale span{margin-right:10px;}
.signatures{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:26px;}
.sig-line{border-top:1.5px solid #333;padding-top:5px;margin-top:34px;text-align:center;font-size:9pt;font-weight:700;}
.unofficial{text-align:center;margin:12px 0 5px;padding:7px;border:1.5px dashed #aaa;border-radius:4px;font-size:8.5pt;color:#666;}
.sheet-footer{border-top:1px solid #ccc;padding-top:5px;margin-top:8px;display:flex;justify-content:space-between;font-size:7.5pt;color:#888;}
@media print{@page{size:A4;margin:0;} .sheet{padding:10mm 12mm;}}
</style></head><body>
<div class="sheet">
<div class="header">
  ${if (logo.isNotEmpty()) "<img src=\"$logo\" class=\"logo\" alt=\"GMS\">" else "<div style=\"width:70px\"></div>"}
  <div class="school-info">
    <div class="school-name">Ganeshman Singh Academy</div>
    <div class="school-sub">Jhiljhille, Jhapa, Province No. 1, Nepal</div>
    <div class="school-tag">Affiliated to CTEVT &nbsp;|&nbsp; Estd. 2060 B.S.</div>
  </div>
  <div style="width:70px"></div>
</div>
<div class="doc-type"><h2>Academic Result Sheet</h2></div>
<div class="info-grid">
  <div class="info-row"><span class="info-label">Student Name:</span><span class="info-val">${esc(studentName)}</span></div>
  <div class="info-row"><span class="info-label">Student ID:</span><span class="info-val">${esc(studentCode)}</span></div>
  <div class="info-row"><span class="info-label">Class / Program:</span><span class="info-val">${esc(className)}</span></div>
  <div class="info-row"><span class="info-label">Examination:</span><span class="info-val">$exam</span></div>
  <div class="info-row"><span class="info-label">Exam Date:</span><span class="info-val">$examDate</span></div>
  <div class="info-row"><span class="info-label">Printed On:</span><span class="info-val">$printed</span></div>
</div>
<table>
<thead><tr><th style="text-align:left;width:34%">Subject</th><th>Full Marks</th><th>Pass Marks</th><th>Obtained</th><th>Percentage</th><th>Grade</th><th>Result</th></tr></thead>
<tbody>$rows</tbody>
<tfoot><tr>
  <td colspan="3">Total</td>
  <td class=c>$tot</td>
  <td class=c>$ovPct</td>
  <td class=c style="font-size:11pt">$ovGrade</td>
  <td class=c style="color:$ovColor">${if (ovPassed) "PASS" else "NG"}</td>
</tr></tfoot>
</table>
<div class="summary-box">
  <div class="sum-item"><div class="sum-label">Total Obtained</div><div class="sum-val">$tot</div></div>
  <div class="sum-item"><div class="sum-label">Percentage</div><div class="sum-val">$ovPct</div></div>
  <div class="sum-item"><div class="sum-label">Overall Grade</div><div class="sum-val">$ovGrade</div></div>
  <div class="sum-item"><span class="result-stamp ${if (ovPassed) "result-pass" else "result-fail"}">${if (ovPassed) "PASSED" else "FAILED"}</span></div>
</div>
<div class="grade-scale">
  <strong>Grade Scale:</strong>
  <span>A+ ≥ 90%</span><span>A ≥ 80%</span><span>B+ ≥ 70%</span><span>B ≥ 60%</span>
  <span>C+ ≥ 50%</span><span>C ≥ 40%</span><span>D ≥ 35%</span><span>NG &lt; 35%</span>
</div>
<div class="signatures">
  <div class="sig-line">Class Teacher</div>
  <div class="sig-line">Principal</div>
</div>
<div class="unofficial"><strong>Note:</strong> This is <strong>NOT an official marksheet</strong>. For official records, contact the school administration.</div>
<div class="sheet-footer">
  <span>Ganeshman Singh Academy — Academic Record (Unofficial)</span>
  <span>Printed: $printed</span>
</div>
</div></body></html>"""
}

@SuppressLint("SetJavaScriptEnabled")
@Composable
fun MarksheetScreen(examId: Int, onBack: () -> Unit) {
    val repo = LocalRepo.current
    val ctx = LocalContext.current
    val state = rememberAsync(examId) { repo.resultDetail(examId) }
    val me by repo.session.data.collectAsState()
    var web by remember { mutableStateOf<WebView?>(null) }

    Column(Modifier.fillMaxSize().background(Canvas).statusBarsPadding()) {
        Row(Modifier.fillMaxWidth().padding(start = 8.dp, end = 8.dp, top = 12.dp, bottom = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, "Back") }
            Text("Marksheet", style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
            IconButton(onClick = {
                web?.let { w ->
                    val pm = ctx.getSystemService(Context.PRINT_SERVICE) as PrintManager
                    val name = "Marksheet"
                    pm.print(name, w.createPrintDocumentAdapter(name), PrintAttributes.Builder().build())
                }
            }) { Icon(Icons.Outlined.Print, "Print or save as PDF") }
        }
        Box(Modifier.weight(1f).fillMaxWidth()) {
            Column(Modifier.fillMaxSize().padding(horizontal = 20.dp)) {
                AsyncContent(state) { r ->
                    val html = remember(r, me) {
                        marksheetHtml(
                            ctx, me?.profile?.fullName.orEmpty(), me?.profile?.code.orEmpty(),
                            me?.profile?.klass?.label ?: r.klass?.label.orEmpty(), r,
                        )
                    }
                    AndroidView(
                        modifier = Modifier.weight(1f).fillMaxWidth(),
                        factory = { c ->
                            WebView(c).apply {
                                settings.javaScriptEnabled = false
                                settings.useWideViewPort = true
                                settings.loadWithOverviewMode = true
                                settings.builtInZoomControls = true
                                settings.displayZoomControls = false
                                setBackgroundColor(android.graphics.Color.WHITE)
                                web = this
                            }
                        },
                        update = { w -> w.loadDataWithBaseURL(null, html, "text/html", "UTF-8", null) },
                    )
                }
            }
        }
    }
}
