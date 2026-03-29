/**
 * data.js
 * All quiz data: clusters/questions, scoring rules, result tiers.
 * Imported by script.js, email-builder.js, and pdf-builder.js.
 */

export const CONFIG = {
    clusters: [
        {
            title: "Company Profile",
            questions: [
                {
                    id: "q1", type: "select", required: true,
                    label: "1. What best describes your role?",
                    error: "Please select your role.",
                    options: [
                        { value: "founder", label: "Founder / Owner" },
                        { value: "coo",     label: "COO / Ops Manager" },
                        { value: "manager", label: "Manager" },
                        { value: "other",   label: "Other" }
                    ]
                },
                {
                    id: "q2", type: "select", required: true,
                    label: "2. Company size?",
                    error: "Please select company size.",
                    options: [
                        { value: "1-5",    label: "1\u20135" },
                        { value: "6-10",   label: "6\u201310" },
                        { value: "11-50",  label: "11\u201350" },
                        { value: "51-200", label: "51\u2013200" },
                        { value: "200+",   label: "200+" }
                    ]
                },
                {
                    id: "q3", type: "select", required: true,
                    label: "3. Primary industry?",
                    error: "Please select your industry.",
                    options: [
                        { value: "ecommerce",    label: "E-commerce" },
                        { value: "agency",       label: "Agency / Services" },
                        { value: "saas",         label: "SaaS / Tech" },
                        { value: "professional", label: "Professional Services" },
                        { value: "other",        label: "Other" }
                    ]
                }
            ]
        },
        {
            title: "Operational Challenges",
            questions: [
                {
                    id: "q4", type: "checkbox", required: true,
                    label: "4. Which areas take up most of your time?",
                    error: "Please select at least one option.",
                    options: [
                        { value: "admin",   label: "Admin / Back office" },
                        { value: "support", label: "Customer support" },
                        { value: "sales",   label: "Sales support" },
                        { value: "finance", label: "Finance / Bookkeeping" },
                        { value: "ops",     label: "Operations / QA" }
                    ]
                },
                {
                    id: "q5", type: "select", required: true,
                    label: "5. What is your biggest operational frustration right now?",
                    error: "Please select an option.",
                    options: [
                        { value: "hiring",   label: "Hiring takes too long" },
                        { value: "costs",    label: "Costs are too high" },
                        { value: "quality",  label: "Quality inconsistency" },
                        { value: "process",  label: "Lack of process" },
                        { value: "timezone", label: "Time zone challenges" }
                    ]
                },
                {
                    id: "q6", type: "select", required: true,
                    label: "6. How severe are these challenges?",
                    error: "Please select severity.",
                    options: [
                        { value: "minimal",  label: "Minimal" },
                        { value: "moderate", label: "Moderate" },
                        { value: "severe",   label: "Severe" }
                    ]
                }
            ]
        },
        {
            title: "Process & Systems",
            questions: [
                {
                    id: "q7", type: "select", required: true,
                    label: "7. Do you currently have documented processes?",
                    error: "Please select an option.",
                    options: [
                        { value: "yes",  label: "Yes, for most tasks" },
                        { value: "some", label: "Some, but incomplete" },
                        { value: "no",   label: "No formal documentation" }
                    ]
                },
                {
                    id: "q8", type: "select", required: true,
                    label: "8. Do you use collaboration tools for remote work?",
                    error: "Please select an option.",
                    options: [
                        { value: "full",    label: "Yes, fully adopted" },
                        { value: "partial", label: "Partially" },
                        { value: "no",      label: "No" }
                    ]
                }
            ]
        },
        {
            title: "Outsourcing Experience & Concerns",
            questions: [
                {
                    id: "q9", type: "select", required: true,
                    label: "9. Have you outsourced before?",
                    error: "Please select an option.",
                    options: [
                        { value: "success", label: "Yes, successfully" },
                        { value: "fail",    label: "Yes, unsuccessfully" },
                        { value: "no",      label: "No" }
                    ]
                },
                {
                    id: "q10", type: "select", required: true,
                    label: "10. What is your main concern about outsourcing?",
                    error: "Please select an option.",
                    options: [
                        { value: "control",  label: "Loss of control" },
                        { value: "quality",  label: "Quality issues" },
                        { value: "comm",     label: "Communication" },
                        { value: "security", label: "Security" },
                        { value: "culture",  label: "Cultural fit" }
                    ]
                }
            ]
        },
        {
            title: "Decision Readiness",
            questions: [
                {
                    id: "q11", type: "select", required: true,
                    label: "11. How comfortable are you with change and risk in operations?",
                    error: "Please select an option.",
                    options: [
                        { value: "high",   label: "Very comfortable" },
                        { value: "medium", label: "Somewhat comfortable" },
                        { value: "low",    label: "Not comfortable" }
                    ]
                },
                {
                    id: "q12", type: "select", required: true,
                    label: "12. Do you have budget allocated for outsourcing?",
                    error: "Please select an option.",
                    options: [
                        { value: "yes",      label: "Yes" },
                        { value: "planning", label: "Planning stage" },
                        { value: "no",       label: "No" }
                    ]
                },
                {
                    id: "q13", type: "select", required: true,
                    label: "13. Timeline for outsourcing?",
                    error: "Please select an option.",
                    options: [
                        { value: "now",       label: "Now" },
                        { value: "soon",      label: "1\u20133 months" },
                        { value: "exploring", label: "Exploring" },
                        { value: "none",      label: "No timeline yet" }
                    ]
                },
                {
                    id: "q14", type: "select", required: true,
                    label: "14. What is your primary goal for outsourcing?",
                    error: "Please select an option.",
                    options: [
                        { value: "cost",      label: "Cost reduction" },
                        { value: "scale",     label: "Scalability" },
                        { value: "focus",     label: "Focus on core business" },
                        { value: "expertise", label: "Access to expertise" }
                    ]
                },
                {
                    id: "q15", type: "select", required: true,
                    label: "15. Are you the final decision-maker for outsourcing?",
                    error: "Please select an option.",
                    options: [
                        { value: "yes",    label: "Yes" },
                        { value: "shared", label: "Shared with others" },
                        { value: "no",     label: "No" }
                    ]
                }
            ]
        },
        {
            title: "Contact Details",
            questions: [
                {
                    id: "q16group", type: "contact",
                    label: "16. Where should we send your results?",
                    fields: [
                        { id: "fullname", type: "text",  name: "fullname", placeholder: "Full Name",    required: true, error: "Please enter your name." },
                        { id: "email",    type: "email", name: "email",    placeholder: "Email",        required: true, error: "Please enter a valid business email." },
                        { id: "phone",    type: "tel",   name: "phone",    placeholder: "Phone Number", required: true, error: "Please enter a valid phone number." },
                        { id: "company",  type: "text",  name: "company",  placeholder: "Company Name", required: true, error: "Please enter your company name." }
                    ]
                }
            ]
        }
    ]
};

export const SCORING_RULES = [
    {
        field: "q7",
        cases: {
            yes:  { pts: 3, msg: "You have documented processes, a strong foundation." },
            some: { pts: 2, msg: "You have partial documentation, but it is incomplete." },
            _:    { pts: 0, msg: "You lack formal documentation, which reduces readiness." }
        }
    },
    {
        field: "q8",
        cases: {
            full:    { pts: 3, msg: "You have fully adopted collaboration tools, supporting outsourcing." },
            partial: { pts: 2, msg: "You use collaboration tools partially, which may limit efficiency." },
            _:       { pts: 0, msg: "You do not use collaboration tools, which could hinder outsourcing success." }
        }
    },
    {
        field: "q9",
        cases: {
            success: { pts: 2, msg: "You have successfully outsourced before, showing proven capability." },
            fail:    { pts: 1, msg: "You have tried outsourcing but faced challenges." },
            _:       { pts: 0, msg: "You have no prior outsourcing experience, which means a learning curve ahead." }
        }
    },
    {
        field: "q12",
        cases: {
            yes:      { pts: 3, msg: "You already have a budget allocated for outsourcing." },
            planning: { pts: 2, msg: "You are in the planning stage for budgeting." },
            _:        { pts: 0, msg: "You have not yet allocated a budget for outsourcing." }
        }
    },
    {
        field: "q13",
        cases: {
            now:  { pts: 3, msg: "Your timeline indicates readiness to start immediately." },
            soon: { pts: 2, msg: "You are considering outsourcing within the next 1\u20133 months." },
            _:    { pts: 0, msg: "You are still exploring and have no fixed timeline." }
        }
    },
    {
        field: "q11",
        cases: {
            high:   { pts: 2, msg: "You are very comfortable with change and risk." },
            medium: { pts: 1, msg: "You are somewhat comfortable with change and risk." },
            _:      { pts: 0, msg: "You are not comfortable with change and risk, which may slow adoption." }
        }
    },
    {
        field: "q6",
        cases: {
            severe:   { pts: 2, msg: "Your operational challenges are severe, increasing urgency." },
            moderate: { pts: 1, msg: "Your operational challenges are moderate." },
            _:        { pts: 0, msg: "Your operational challenges are minimal." }
        }
    }
];

/*
   TIERS
   Each CTA has an "action" key:
     "schedule"  → email admin  (subject: "Discovery Call for Outsourcing Ready")
     "consult"   → email admin  (subject: "Consultation for <tier title>")
     "download"  → generate & download PDF via pdf-builder.js
*/
export const TIERS = [
    {
        min:      14,
        title:    "You are Outsourcing Ready!",
        body:     "Your organization already has the operational maturity needed to outsource successfully. Documented processes, collaboration tools, and leadership readiness indicate that external teams can integrate smoothly into your workflow. The next step is identifying the right functions to outsource and building a structured onboarding plan.",
        goalLine: "outsourcing can help accelerate this by reallocating internal resources to higher-value strategic work.",
        ctas:     [{ label: "Schedule Your Strategy Call", action: "schedule" }]
    },
    {
        min:      9,
        title:    "You are Partially Ready.",
        body:     "Your company has the foundations for outsourcing, but a few operational gaps could slow down success. Strengthening documentation, improving communication workflows, and clarifying responsibilities will make outsourcing significantly more effective.",
        goalLine: "preparing these systems will ensure outsourcing delivers the results you're aiming for.",
        ctas:     [{ label: "Book a Consultation", action: "consult" }, { label: "Download Our Readiness Guide", action: "download" }]
    },
    {
        min:      0,
        title:    "You are Not Ready Yet.",
        body:     "Before outsourcing, it would be beneficial to strengthen your internal operational structure. Clear processes, defined roles, and consistent workflows create the foundation external teams rely on. Building these systems first will significantly increase outsourcing success.",
        goalLine: "outsourcing can help you achieve your goal more effectively once your internal operations are stronger.",
        ctas:     [{ label: "Book a Consultation", action: "consult" }, { label: "Download Our Readiness Guide", action: "download" }]
    }
];

