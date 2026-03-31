/**
 * pdf-builder.js
 * Generates the Outsourcing Readiness Guide PDF using jsPDF.
 * Imported by main.js and called when the user clicks "Download Our Readiness Guide".
 * Replace placeholder copy with real guide content when ready.
 */

import { jsPDF } from 'jspdf';
import { CONFIG } from './data.js';

/* ── Brand colours ───────────────────────────────────────────── */
var PDF = {
    navy:       [15,  31,  61],   // #0f1f3d
    navyMid:    [26,  50,  96],   // #1a3260
    accent:     [84, 200, 239],   // #54c8ef
    white:      [255, 255, 255],
    offWhite:   [244, 246, 251],  // #f4f6fb
    muted:      [107, 122, 153],  // #6b7a99
    text:       [30,  40,  60],
    lightText:  [100, 120, 150]
};

/* ── Layout constants ────────────────────────────────────────── */
var PW = 210;   // page width  (A4 mm)
var PH = 297;   // page height (A4 mm)
var ML = 18;    // left margin
var MR = 18;    // right margin
var CW = PW - ML - MR;  // content width

/* ── Helper: set fill + draw colour from [r,g,b] array ──────── */
function pdfSetFill(doc, rgb)   { doc.setFillColor(rgb[0],  rgb[1],  rgb[2]);  }
function pdfSetDraw(doc, rgb)   { doc.setDrawColor(rgb[0],  rgb[1],  rgb[2]);  }
function pdfSetText(doc, rgb)   { doc.setTextColor(rgb[0],  rgb[1],  rgb[2]);  }

/* ── Cover page ──────────────────────────────────────────────── */
function pdfCover(doc) {

    // Full navy background
    pdfSetFill(doc, PDF.navy);
    doc.rect(0, 0, PW, PH, "F");

    // Accent strip top
    pdfSetFill(doc, PDF.accent);
    doc.rect(0, 0, PW, 4, "F");

    // Accent strip bottom
    doc.rect(0, PH - 4, PW, 4, "F");

    // Decorative diagonal block (top-right)
    pdfSetFill(doc, PDF.navyMid);
    doc.triangle(PW - 80, 0, PW, 0, PW, 90, "F");

    // Logo area (text placeholder)
    pdfSetText(doc, PDF.accent);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(11);
    doc.text("MAGELLAN SOLUTIONS", ML, 30);

    // Title
    pdfSetText(doc, PDF.white);
    doc.setFontSize(32);
    doc.setFont("helvetica", "bold");
    doc.text("Outsourcing", ML, 110);
    doc.text("Readiness", ML, 125);

    pdfSetText(doc, PDF.accent);
    doc.text("Results", ML, 140);

    // Subtitle
    pdfSetText(doc, [180, 195, 220]);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(12);
    doc.text("Your personalised outsourcing readiness report.", ML, 158);

    // Edition note
    // pdfSetText(doc, PDF.muted);
    // doc.setFontSize(9);
    // doc.text("Magellan Solutions  ·  " + new Date().getFullYear(), ML, PH - 14);
}

/* ── Section heading ─────────────────────────────────────────── */
function pdfSectionHeading(doc, y, text) {
    // Accent underline bar
    pdfSetFill(doc, PDF.accent);
    doc.rect(ML, y - 1, 4, 8, "F");

    pdfSetText(doc, PDF.navy);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(14);
    doc.text(text, ML + 8, y + 5);

    return y + 18;
}

/* ── Body paragraph ──────────────────────────────────────────── */
function pdfParagraph(doc, y, text, opts) {
    var options  = opts || {};
    var maxWidth = options.maxWidth || CW;
    var fontSize = options.fontSize || 10;
    var color    = options.color    || PDF.text;
    var bold     = options.bold     || false;

    pdfSetText(doc, color);
    doc.setFont("helvetica", bold ? "bold" : "normal");
    doc.setFontSize(fontSize);

    var lines = doc.splitTextToSize(text, maxWidth);
    doc.text(lines, ML, y);
    return y + lines.length * (fontSize * 0.45) + 4;
}

/* ── Callout box ─────────────────────────────────────────────── */
function pdfCallout(doc, y, text, height) {
    var h = height || 20;
    pdfSetFill(doc, PDF.offWhite);
    pdfSetDraw(doc, PDF.accent);
    doc.setLineWidth(0.5);
    doc.roundedRect(ML, y, CW, h, 3, 3, "FD");

    pdfSetText(doc, PDF.navy);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9.5);
    var lines = doc.splitTextToSize(text, CW - 10);
    doc.text(lines, ML + 5, y + 7);
    return y + h + 6;
}

/* ── Bullet list ─────────────────────────────────────────────── */
function pdfBullets(doc, y, items) {
    pdfSetText(doc, PDF.text);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(10);

    items.forEach(function (item) {
        // Accent bullet dot
        pdfSetFill(doc, PDF.accent);
        doc.circle(ML + 2, y - 1.5, 1.2, "F");

        pdfSetText(doc, PDF.text);
        var lines = doc.splitTextToSize(item, CW - 10);
        doc.text(lines, ML + 8, y);
        y += lines.length * 5.5 + 2;
    });

    return y + 2;
}

/* ── Horizontal rule ─────────────────────────────────────────── */
function pdfRule(doc, y) {
    pdfSetDraw(doc, [220, 225, 235]);
    doc.setLineWidth(0.3);
    doc.line(ML, y, ML + CW, y);
    return y + 8;
}

/* ── Numbered step box ───────────────────────────────────────── */
function pdfStepBox(doc, y, num, title, body) {
    var boxH = 28;
    pdfSetFill(doc, PDF.offWhite);
    doc.roundedRect(ML, y, CW, boxH, 3, 3, "F");

    // Number circle
    pdfSetFill(doc, PDF.navy);
    doc.circle(ML + 10, y + 14, 7, "F");
    pdfSetText(doc, PDF.white);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(10);
    doc.text(String(num), ML + 10, y + 17.5, { align: "center" });

    // Title
    pdfSetText(doc, PDF.navy);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(11);
    doc.text(title, ML + 22, y + 12);

    // Body
    pdfSetText(doc, PDF.muted);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    var lines = doc.splitTextToSize(body, CW - 28);
    doc.text(lines, ML + 22, y + 20);

    return y + boxH + 5;
}

/* ── Page header (for inner pages) ──────────────────────────── */
function pdfPageHeader(doc, label) {
    pdfSetFill(doc, PDF.navy);
    doc.rect(0, 0, PW, 18, "F");
    pdfSetText(doc, PDF.accent);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(8);
    doc.text("MAGELLAN SOLUTIONS  ·  OUTSOURCING READINESS RESULTS", ML, 11);

    pdfSetText(doc, [180, 195, 220]);
    doc.setFont("helvetica", "normal");
    doc.text(label, PW - MR, 11, { align: "right" });
}

/* ── Page footer ─────────────────────────────────────────────── */
function pdfPageFooter(doc, pageNum) {
    pdfSetFill(doc, PDF.offWhite);
    doc.rect(0, PH - 12, PW, 12, "F");
    pdfSetText(doc, PDF.muted);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(8);
    doc.text("© " + new Date().getFullYear() + " Magellan Solutions. All rights reserved.", ML, PH - 4.5);
    doc.text("Page " + pageNum, PW - MR, PH - 4.5, { align: "right" });
}

/* ── Tier result card (personalised page) ────────────────────── */
function pdfTierCard(doc, y, tierTitle, tierBody, goalLine, goalAnswer, insights) {

    // Title banner
    pdfSetFill(doc, PDF.navy);
    doc.roundedRect(ML, y, CW, 14, 3, 3, "F");
    pdfSetText(doc, PDF.accent);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(13);
    doc.text(tierTitle, ML + 6, y + 9.5);
    y += 20;

    y = pdfParagraph(doc, y, tierBody, { color: PDF.text });
    y += 4;

    y = pdfCallout(
        doc, y,
        "Since your primary goal is " + goalAnswer + ", " + goalLine,
        18
    );

    y = pdfParagraph(doc, y, "Key Insights:", { bold: true, color: PDF.navyMid });
    y = pdfBullets(doc, y + 2, insights);

    return y;
}


/* ================================================================
   PUBLIC: generateReadinessGuidePDF(formData, tierTitle, tierBody,
           goalLine, insights, itiInstance)
   Downloads a personalised Readiness Guide PDF.
   If formData is null, generates a generic blank-branded guide.
   ================================================================ */
export function generateResultsPDF(formData, tierTitle, tierBody, goalLine, insights, itiInstance) {

    const doc = new jsPDF({ unit: "mm", format: "a4" });
    var y;

    /* ── Page 1: Cover ──────────────────────────────────────────── */
    pdfCover(doc);
    pdfPageFooter(doc, 1);

    /* ── Page 2: What is Outsourcing Readiness? ─────────────────── */
    doc.addPage();
    pdfPageHeader(doc, "INTRODUCTION");
    pdfPageFooter(doc, 2);
    y = 30;

    y = pdfSectionHeading(doc, y, "What is Outsourcing Readiness?");

    y = pdfParagraph(doc, y,
        "Outsourcing readiness refers to the degree to which your organisation has the operational, " +
        "financial, and cultural foundations required to successfully delegate functions to an external team. " +
        "It is not simply a question of whether you want to outsource — it is about whether your organisation " +
        "is structurally prepared to make outsourcing work.",
        { color: PDF.text }
    );
    y += 4;

    y = pdfCallout(doc, y,
        "Companies that outsource without readiness often face quality issues, communication breakdowns, " +
        "and wasted investment. This guide helps you avoid those pitfalls.",
        22
    );

    y = pdfParagraph(doc, y,
        "The Magellan Solutions Readiness Assessment evaluates five dimensions:",
        { bold: true, color: PDF.navy }
    );
    y = pdfBullets(doc, y + 2, [
        "Company Profile — who you are and your industry context",
        "Operational Challenges — the pain points driving your outsourcing interest",
        "Process & Systems — your documentation and tooling maturity",
        "Outsourcing Experience & Concerns — what you know and what worries you",
        "Decision Readiness — your budget, timeline, and authority to act"
    ]);

    /* ── Page 3: The Three Tiers ─────────────────────────────────── */
    doc.addPage();
    pdfPageHeader(doc, "READINESS TIERS");
    pdfPageFooter(doc, 3);
    y = 30;

    y = pdfSectionHeading(doc, y, "The Three Readiness Tiers");

    y = pdfParagraph(doc, y,
        "Your assessment score places you in one of three tiers. Understanding your tier is the " +
        "starting point for knowing what to do next.",
        { color: PDF.text }
    );
    y += 6;

    y = pdfStepBox(doc, y, 1, "Outsourcing Ready  (Score 14–16)",
        "Strong processes, tools, and decision authority are in place. You can begin outsourcing now with high confidence of success.");
    y = pdfStepBox(doc, y, 2, "Partially Ready  (Score 9–13)",
        "Good foundations exist but gaps in documentation, tools, or buy-in may limit early results. Targeted preparation will significantly improve outcomes.");
    y = pdfStepBox(doc, y, 3, "Not Ready Yet  (Score 0–8)",
        "Outsourcing before addressing key structural gaps often leads to failure. A focused readiness roadmap should come first.");

    y += 4;
    y = pdfRule(doc, y);

    y = pdfParagraph(doc, y,
        "Regardless of your tier, outsourcing is achievable. The tiers help you time your decision " +
        "and set realistic expectations — not to discourage action, but to make your action count.",
        { color: PDF.muted }
    );

    /* ── Page 4: What to Do Next ─────────────────────────────────── */
    doc.addPage();
    pdfPageHeader(doc, "NEXT STEPS");
    pdfPageFooter(doc, 4);
    y = 30;

    y = pdfSectionHeading(doc, y, "What to Do Next");

    [
        {
            step: 1,
            title: "Review your score and tier",
            body:  "Understand the specific factors that influenced your result. Each insight in your results indicates an area of strength or a gap to address."
        },
        {
            step: 2,
            title: "Address your critical gaps first",
            body:  "If you scored Partially Ready or Not Ready, prioritise closing the gaps with the highest impact: documentation, tooling, and stakeholder alignment."
        },
        {
            step: 3,
            title: "Define the scope of outsourcing",
            body:  "Identify 1–3 specific functions to outsource initially. Start narrow, prove the model, then expand. Avoid trying to outsource everything at once."
        },
        {
            step: 4,
            title: "Build your selection criteria",
            body:  "Determine what you need in an outsourcing partner: industry experience, communication standards, team size, pricing model, and cultural alignment."
        },
        {
            step: 5,
            title: "Engage with a trusted provider",
            body:  "Magellan Solutions specialises in helping SMEs and growing companies outsource the right way. Schedule a strategy call to discuss your specific situation."
        }
    ].forEach(function (item) {
        y = pdfStepBox(doc, y, item.step, item.title, item.body);
    });

    /* ── Page 5: Personalised Results (only if formData provided) ── */
    if (formData && tierTitle) {
        doc.addPage();
        pdfPageHeader(doc, "YOUR RESULTS");
        pdfPageFooter(doc, 5);
        y = 30;

        y = pdfSectionHeading(doc, y, "Your Personalised Assessment Results");

        var fullname   = formData.get("fullname") || "";
        var goalAnswer = "";
        CONFIG.clusters.forEach(function (cluster) {
            cluster.questions.forEach(function (q) {
                if (q.id === "q14" && q.options) {
                    var opt = q.options.find(function (o) { return o.value === formData.get("q14"); });
                    if (opt) goalAnswer = opt.label;
                }
            });
        });

        if (fullname) {
            y = pdfParagraph(doc, y, "Prepared for: " + fullname +
                (formData.get("company") ? "  ·  " + formData.get("company") : ""),
                { bold: true, color: PDF.navyMid, fontSize: 10 }
            );
            y += 4;
        }

        y = pdfTierCard(
            doc, y,
            tierTitle, tierBody, goalLine, goalAnswer,
            insights || []
        );
    }

    /* ── Page 6 (or 5 if no personal page): About Magellan ─────── */
    doc.addPage();
    var lastPage = doc.internal.getNumberOfPages();
    pdfPageHeader(doc, "ABOUT MAGELLAN");
    pdfPageFooter(doc, lastPage);
    y = 30;

    y = pdfSectionHeading(doc, y, "About Magellan Solutions");

    y = pdfParagraph(doc, y,
        "Magellan Solutions is a Philippines-based business process outsourcing (BPO) company " +
        "founded in 2005, specialising in delivering scalable outsourcing solutions to small and " +
        "medium-sized businesses worldwide. With 500+ dedicated staff and nearly two decades of " +
        "industry experience, Magellan Solutions partners with clients across the US, Australia, " +
        "UK, and beyond to help them reduce operational costs and focus on growth.",
        { color: PDF.text }
    );
    y += 6;

    y = pdfParagraph(doc, y,
        "We are ISO-certified and HIPAA-compliant, with a track record of delivering measurable " +
        "results for clients in healthcare, e-commerce, professional services, SaaS, and more. " +
        "Our people-first culture, robust quality assurance processes, and transparent reporting " +
        "make us a trusted long-term partner — not just a vendor.",
        { color: PDF.text }
    );
    y += 6;

    y = pdfParagraph(doc, y, "Our Core Services:", { bold: true, color: PDF.navy });
    y = pdfBullets(doc, y + 2, [
        "Customer Support & Technical Help Desk",
        "Finance & Accounting (Bookkeeping, AP/AR, Payroll)",
        "Sales Support & Lead Generation / Appointment Setting",
        "Back Office & Data Management",
        "Healthcare Support (Medical Billing, Transcription)",
        "Digital Marketing & Content Operations",
        "IT & Software Support"
    ]);
    y += 4;

    // Dynamic callout — text depends on the user's result tier
    var calloutText;
    if ( tierTitle && tierTitle.toLowerCase().indexOf('ready!') !== -1 ) {
        // "You are Outsourcing Ready!" — prompt to schedule a strategy call
        calloutText =
            "Your business is ready to outsource. Visit magellan-solutions.com " +
            "or request a strategy call to start building your custom outsourcing solution.";
    } else if ( tierTitle && tierTitle.toLowerCase().indexOf('partially') !== -1 ) {
        // "You are Partially Ready." — prompt to book a consultation
        calloutText =
            "You're almost there. Book a consultation at magellan-solutions.com " +
            "and we'll help you close the gaps before you outsource.";
    } else {
        // "You are Not Ready Yet." — prompt to book a consultation
        calloutText =
            "Building the right foundations makes all the difference. Book a consultation " +
            "at magellan-solutions.com and we'll create a readiness roadmap for your business.";
    }
    y = pdfCallout(doc, y, calloutText, 20);

    /* ── Download ────────────────────────────────────────────────── */
    // Results PDF — personalised with the user's name (separate from Readiness Guide PDF)
    var filename = formData && formData.get("fullname")
        ? "Magellan-Readiness-Results-" + formData.get("fullname").replace(/\s+/g, "-") + ".pdf"
        : "Magellan-Outsourcing-Readiness-Results.pdf";

    // Return base64 string for email attachment instead of auto-saving
    return doc.output('datauristring').split(',')[1]; // pure base64
}

/**
 * generateResultsPDFBase64
 * Same as generateResultsPDF but returns a base64 string
 * so wp-service.js can send it to the PHP endpoint as an email attachment.
 * @returns {string} base64-encoded PDF
 */
export function generateResultsPDFBase64(formData, tierTitle, tierBody, goalLine, insights, itiInstance) {
    return generateResultsPDF(formData, tierTitle, tierBody, goalLine, insights, itiInstance);
}

/* ================================================================
   PUBLIC: generateReadinessGuidePDF()
   Downloads a generic (non-personalised) Outsourcing Readiness Guide.
   This is the PDF triggered by the "Download Our Readiness Guide" CTA.
   Blank-branded — no user results page, separate filename.
   ================================================================ */
export function generateReadinessGuidePDF() {

    const doc = new jsPDF({ unit: "mm", format: "a4" });
    var y;

    /* ── Page 1: Cover ──────────────────────────────────────────── */
    // Navy background + accent strips
    pdfSetFill(doc, PDF.navy);
    doc.rect(0, 0, PW, PH, "F");
    pdfSetFill(doc, PDF.accent);
    doc.rect(0, 0, PW, 4, "F");
    doc.rect(0, PH - 4, PW, 4, "F");
    pdfSetFill(doc, PDF.navyMid);
    doc.triangle(PW - 80, 0, PW, 0, PW, 90, "F");

    // Logo
    pdfSetText(doc, PDF.accent);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(11);
    doc.text("MAGELLAN SOLUTIONS", ML, 30);

    // Title
    pdfSetText(doc, PDF.white);
    doc.setFontSize(32);
    doc.text("Outsourcing", ML, 110);
    doc.text("Readiness", ML, 125);
    pdfSetText(doc, PDF.accent);
    doc.text("Guide", ML, 140);

    // Subtitle
    pdfSetText(doc, [180, 195, 220]);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(12);
    doc.text("Know where you stand before you outsource.", ML, 158);

    pdfSetText(doc, PDF.muted);
    doc.setFontSize(9);
    doc.text("Magellan Solutions  ·  " + new Date().getFullYear(), ML, PH - 14);
    pdfPageFooter(doc, 1);

    /* ── Page 2: What is Outsourcing Readiness? ─────────────────── */
    doc.addPage();
    pdfPageHeader(doc, "INTRODUCTION");
    pdfPageFooter(doc, 2);
    y = 30;

    y = pdfSectionHeading(doc, y, "What is Outsourcing Readiness?");
    y = pdfParagraph(doc, y,
        "Outsourcing readiness refers to the degree to which your organisation has the operational, " +
        "financial, and cultural foundations required to successfully delegate functions to an external team. " +
        "It is not simply a question of whether you want to outsource — it is about whether your organisation " +
        "is structurally prepared to make outsourcing work.",
        { color: PDF.text }
    );
    y += 4;
    y = pdfCallout(doc, y,
        "Companies that outsource without readiness often face quality issues, communication breakdowns, " +
        "and wasted investment. This guide helps you avoid those pitfalls.",
        22
    );
    y = pdfParagraph(doc, y,
        "The Magellan Solutions Readiness Assessment evaluates five dimensions:",
        { bold: true, color: PDF.navy }
    );
    y = pdfBullets(doc, y + 2, [
        "Company Profile — who you are and your industry context",
        "Operational Challenges — the pain points driving your outsourcing interest",
        "Process & Systems — your documentation and tooling maturity",
        "Outsourcing Experience & Concerns — what you know and what worries you",
        "Decision Readiness — your budget, timeline, and authority to act"
    ]);

    /* ── Page 3: The Three Tiers ─────────────────────────────────── */
    doc.addPage();
    pdfPageHeader(doc, "READINESS TIERS");
    pdfPageFooter(doc, 3);
    y = 30;

    y = pdfSectionHeading(doc, y, "The Three Readiness Tiers");
    y = pdfParagraph(doc, y,
        "Your assessment score places you in one of three tiers. Understanding your tier is the " +
        "starting point for knowing what to do next.",
        { color: PDF.text }
    );
    y += 6;
    y = pdfStepBox(doc, y, 1, "Outsourcing Ready  (Score 14–16)",
        "Strong processes, tools, and decision authority are in place. You can begin outsourcing now with high confidence of success.");
    y = pdfStepBox(doc, y, 2, "Partially Ready  (Score 9–13)",
        "Good foundations exist but gaps in documentation, tools, or buy-in may limit early results. Targeted preparation will significantly improve outcomes.");
    y = pdfStepBox(doc, y, 3, "Not Ready Yet  (Score 0–8)",
        "Outsourcing before addressing key structural gaps often leads to failure. A focused readiness roadmap should come first.");
    y += 4;
    y = pdfRule(doc, y);
    y = pdfParagraph(doc, y,
        "Regardless of your tier, outsourcing is achievable. The tiers help you time your decision " +
        "and set realistic expectations — not to discourage action, but to make your action count.",
        { color: PDF.muted }
    );

    /* ── Page 4: What to Do Next ─────────────────────────────────── */
    doc.addPage();
    pdfPageHeader(doc, "NEXT STEPS");
    pdfPageFooter(doc, 4);
    y = 30;

    y = pdfSectionHeading(doc, y, "What to Do Next");
    [
        { step: 1, title: "Review your score and tier",
          body: "Understand the specific factors that influenced your result. Each insight in your results indicates an area of strength or a gap to address." },
        { step: 2, title: "Address your critical gaps first",
          body: "If you scored Partially Ready or Not Ready, prioritise closing the gaps with the highest impact: documentation, tooling, and stakeholder alignment." },
        { step: 3, title: "Define the scope of outsourcing",
          body: "Identify 1–3 specific functions to outsource initially. Start narrow, prove the model, then expand. Avoid trying to outsource everything at once." },
        { step: 4, title: "Build your selection criteria",
          body: "Determine what you need in an outsourcing partner: industry experience, communication standards, team size, pricing model, and cultural alignment." },
        { step: 5, title: "Engage with a trusted provider",
          body: "Magellan Solutions specialises in helping SMEs and growing companies outsource the right way. Schedule a strategy call to discuss your specific situation." }
    ].forEach(function (item) {
        y = pdfStepBox(doc, y, item.step, item.title, item.body);
    });

    /* ── Page 5: About Magellan ─────────────────────────────────── */
    doc.addPage();
    pdfPageHeader(doc, "ABOUT MAGELLAN");
    pdfPageFooter(doc, 5);
    y = 30;

    y = pdfSectionHeading(doc, y, "About Magellan Solutions");
    y = pdfParagraph(doc, y,
        "Magellan Solutions is a Philippines-based business process outsourcing (BPO) company " +
        "founded in 2005, specialising in delivering scalable outsourcing solutions to small and " +
        "medium-sized businesses worldwide. With 500+ dedicated staff and nearly two decades of " +
        "industry experience, Magellan Solutions partners with clients across the US, Australia, " +
        "UK, and beyond to help them reduce operational costs and focus on growth.",
        { color: PDF.text }
    );
    y += 6;
    y = pdfParagraph(doc, y,
        "We are ISO-certified and HIPAA-compliant, with a track record of delivering measurable " +
        "results for clients in healthcare, e-commerce, professional services, SaaS, and more. " +
        "Our people-first culture, robust quality assurance processes, and transparent reporting " +
        "make us a trusted long-term partner — not just a vendor.",
        { color: PDF.text }
    );
    y += 6;
    y = pdfParagraph(doc, y, "Our Core Services:", { bold: true, color: PDF.navy });
    y = pdfBullets(doc, y + 2, [
        "Customer Support & Technical Help Desk",
        "Finance & Accounting (Bookkeeping, AP/AR, Payroll)",
        "Sales Support & Lead Generation / Appointment Setting",
        "Back Office & Data Management",
        "Healthcare Support (Medical Billing, Transcription)",
        "Digital Marketing & Content Operations",
        "IT & Software Support"
    ]);
    y += 4;
    y = pdfCallout(doc, y,
        "Visit magellan-solutions.com or schedule a free strategy call to learn how " +
        "we can build a custom outsourcing solution tailored to your business.",
        20
    );

    doc.save("Magellan-Outsourcing-Readiness-Guide.pdf");
}
