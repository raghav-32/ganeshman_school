/* Shared result sheet (marksheet) used by the admin Marks & Results page and the student My Results page.
   gmsOpenMarksheet(student, marks, subjects, ctx)
     student  {first_name,last_name,student_id,class_name,section}
     marks    [{subject_id, marks_obtained}]
     subjects [{id, subject_name, full_marks, pass_marks}]
     ctx      {examName, examType, examDate, printedOn, logo} */
function gmsGradeCalc(obtained, full) {
    if (!full) return 'NG';
    var pct = (obtained / full) * 100;
    if (pct >= 90) return 'A+';
    if (pct >= 80) return 'A';
    if (pct >= 70) return 'B+';
    if (pct >= 60) return 'B';
    if (pct >= 50) return 'C+';
    if (pct >= 40) return 'C';
    if (pct >= 35) return 'D';
    return 'NG';
}

function gmsOpenMarksheet(student, marks, subjects, ctx) {
    var marksMap = {};
    marks.forEach(function(m){ marksMap[m.subject_id] = m; });

    var totalObt = 0, totalFull = 0, anyFailed = false;
    var rows = '';

    subjects.forEach(function(sub) {
        var m    = marksMap[sub.id];
        var full = parseInt(sub.full_marks) || 100;
        var pass = parseInt(sub.pass_marks) || 40;
        if (!m) {
            totalFull += full;
            rows += '<tr><td>' + sub.subject_name + '</td>'
                  + '<td style="text-align:center">' + full + '</td>'
                  + '<td style="text-align:center">' + pass + '</td>'
                  + '<td style="text-align:center;color:#999">–</td>'
                  + '<td style="text-align:center;color:#999">–</td>'
                  + '<td style="text-align:center;color:#999">–</td>'
                  + '<td style="text-align:center;color:#999">–</td></tr>';
        } else {
            var obt   = parseFloat(m.marks_obtained);
            var passed = obt >= pass;
            var grade  = gmsGradeCalc(obt, full);
            var pct    = full > 0 ? (obt/full*100).toFixed(1)+'%' : '–';
            var obtDisp = Number.isInteger(obt) ? obt : obt;
            if (!passed) anyFailed = true;
            totalObt  += obt;
            totalFull += full;
            rows += '<tr>'
                  + '<td>' + sub.subject_name + '</td>'
                  + '<td style="text-align:center">' + full + '</td>'
                  + '<td style="text-align:center">' + pass + '</td>'
                  + '<td style="text-align:center;font-weight:700;color:' + (passed?'#166534':'#991b1b') + '">' + Math.floor(obt) + '</td>'
                  + '<td style="text-align:center">' + pct + '</td>'
                  + '<td style="text-align:center;font-weight:700;color:' + (!passed?'#991b1b':'#1a3c5e') + '">' + grade + '</td>'
                  + '<td style="text-align:center;font-weight:700;color:' + (passed?'#166534':'#991b1b') + '">' + (passed?'Pass':'NG') + '</td>'
                  + '</tr>';
        }
    });

    var ovGrade  = gmsGradeCalc(totalObt, totalFull);
    var ovPassed = !anyFailed && ovGrade !== 'NG';
    var ovPct    = totalFull > 0 ? (totalObt/totalFull*100).toFixed(1)+'%' : '–';
    var ovColor  = ovPassed ? '#166534' : '#991b1b';

    var html = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Result Sheet - ` + student.first_name + ` ` + student.last_name + `</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Times New Roman',serif;background:#fff;color:#1a1a1a;}
.sheet{width:210mm;min-height:297mm;margin:0 auto;padding:16mm 14mm;}
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
@media print{.no-print{display:none!important;} @page{size:A4;margin:0;} .sheet{padding:10mm 12mm;}}
</style></head><body>
<div class="sheet">
<div class="no-print" style="text-align:right;margin-bottom:10px;">
  <button onclick="window.print()" style="padding:7px 20px;background:#1a3c5e;color:#fff;border:none;border-radius:4px;font-size:11pt;cursor:pointer;font-family:inherit;">🖨 Print</button>
</div>
<div class="header">
  ` + (ctx.logo ? '<img src="' + ctx.logo + '" class="logo" alt="GMS">' : '<div style="width:70px"></div>') + `
  <div class="school-info">
    <div class="school-name">Ganeshman Singh Academy</div>
    <div class="school-sub">Jhiljhille, Jhapa, Province No. 1, Nepal</div>
    <div class="school-tag">Affiliated to CTEVT &nbsp;|&nbsp; Estd. 2060 B.S.</div>
  </div>
  <div style="width:70px"></div>
</div>
<div class="doc-type"><h2>Academic Result Sheet</h2></div>
<div class="info-grid">
  <div class="info-row"><span class="info-label">Student Name:</span><span class="info-val">` + student.first_name + ' ' + student.last_name + `</span></div>
  <div class="info-row"><span class="info-label">Student ID:</span><span class="info-val">` + student.student_id + `</span></div>
  <div class="info-row"><span class="info-label">Class / Program:</span><span class="info-val">` + (student.class_name||'') + ' ' + (student.section||'') + `</span></div>
  <div class="info-row"><span class="info-label">Examination:</span><span class="info-val">` + ctx.examName + ' (' + ctx.examType + ')' + `</span></div>
  <div class="info-row"><span class="info-label">Exam Date:</span><span class="info-val">` + ctx.examDate + `</span></div>
  <div class="info-row"><span class="info-label">Printed On:</span><span class="info-val">` + ctx.printedOn + `</span></div>
</div>
<table>
<thead><tr><th style="text-align:left;width:34%">Subject</th><th>Full Marks</th><th>Pass Marks</th><th>Obtained</th><th>Percentage</th><th>Grade</th><th>Result</th></tr></thead>
<tbody>` + rows + `</tbody>
<tfoot><tr>
  <td colspan="3" style="padding:7px 9px;border:1px solid #aaa">Total</td>
  <td style="text-align:center;padding:7px 9px;border:1px solid #aaa">` + Math.floor(totalObt) + ` / ` + totalFull + `</td>
  <td style="text-align:center;padding:7px 9px;border:1px solid #aaa">` + ovPct + `</td>
  <td style="text-align:center;padding:7px 9px;border:1px solid #aaa;font-size:11pt">` + ovGrade + `</td>
  <td style="text-align:center;padding:7px 9px;border:1px solid #aaa;color:` + ovColor + `">` + (ovPassed?'PASS':'NG') + `</td>
</tr></tfoot>
</table>
<div class="summary-box">
  <div class="sum-item"><div class="sum-label">Total Obtained</div><div class="sum-val">` + Math.floor(totalObt) + ` / ` + totalFull + `</div></div>
  <div class="sum-item"><div class="sum-label">Percentage</div><div class="sum-val">` + ovPct + `</div></div>
  <div class="sum-item"><div class="sum-label">Overall Grade</div><div class="sum-val">` + ovGrade + `</div></div>
  <div class="sum-item"><span class="result-stamp ` + (ovPassed?'result-pass':'result-fail') + `">` + (ovPassed?'PASSED':'FAILED') + `</span></div>
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
  <span>Printed: ` + ctx.printedOn + `</span>
</div>
</div></body></html>`;

    var win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
}
