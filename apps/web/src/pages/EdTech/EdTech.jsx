import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  BookOpen,
  BriefcaseBusiness,
  Building2,
  CheckCircle2,
  Clock3,
  GraduationCap,
  Loader2,
  Search,
  ShieldCheck,
  Sparkles,
  Trophy,
} from "lucide-react";
import Image from "../../assets/Image";
import { useAuth } from "../../context/AuthContext";
import {
  applyExaSkillsOpportunity,
  purchaseExaSkillsCourse,
  getExaSkillsHome,
  submitExaSkillsChallenge,
} from "../../services/exaSkillsApi";

const emptyHome = {
  summary: {},
  continue_learning: [],
  categories: [],
  featured_courses: [],
  challenges: [],
  opportunities: [],
  credentials: [],
  supported: {},
};

function normalizeList(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data)) return value.data;
  return [];
}

function courseKey(course) {
  return course?.slug || course?.id || "course";
}

function formatMoney(value, asset = "USDT") {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric <= 0) return "Free";
  return `${numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${asset}`;
}

function ExaSkills({
  onBack,
  onOpenBecomeEducator,
  onOpenApplyScholarship,
  onOpenCourseUpload,
  onOpenStartLearning = () => {},
  onOpenInstructorDashboard = () => {},
}) {
  const { apiBaseUrl, token } = useAuth();
  const [data, setData] = useState(emptyHome);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [query, setQuery] = useState("");
  const [enrollingId, setEnrollingId] = useState(null);
  const [actionId, setActionId] = useState(null);
  const [notice, setNotice] = useState("");

  const loadHome = async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await getExaSkillsHome({ apiBaseUrl, token });
      setData({ ...emptyHome, ...(payload.data || {}) });
    } catch (err) {
      setError(err?.message || "ExaSkills is temporarily unavailable.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadHome();
  }, [apiBaseUrl, token]);

  const categories = normalizeList(data.categories);
  const courses = normalizeList(data.featured_courses);
  const challenges = normalizeList(data.challenges);
  const opportunities = normalizeList(data.opportunities);
  const continueLearning = normalizeList(data.continue_learning);

  const filteredCourses = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return courses;
    return courses.filter((course) => `${course.title || ""} ${course.description || ""} ${course.instructor_name || ""}`.toLowerCase().includes(term));
  }, [courses, query]);

  const handleEnroll = async (course) => {
    const id = courseKey(course);
    setNotice("");
    setEnrollingId(id);
    try {
      await purchaseExaSkillsCourse({ apiBaseUrl, token, course: id, idempotencyKey: crypto?.randomUUID?.() || `skills-${Date.now()}` });
      setNotice("Course access confirmed. Your learning dashboard has been updated.");
      await loadHome();
    } catch (err) {
      setNotice(err?.message || "Course access could not be completed.");
    } finally {
      setEnrollingId(null);
    }
  };

  const handleChallengeSubmit = async (challenge) => {
    const id = challenge?.slug || challenge?.id;
    if (!id) return;
    setNotice("");
    setActionId(`challenge-${id}`);
    try {
      await submitExaSkillsChallenge({
        apiBaseUrl,
        token,
        challenge: id,
        body: { description: "I want to participate in this ExaSkills challenge." },
      });
      setNotice("Challenge participation saved. You can refine your submission before the deadline.");
      await loadHome();
    } catch (err) {
      setNotice(err?.message || "Challenge submission could not be saved.");
    } finally {
      setActionId(null);
    }
  };

  const handleOpportunityApply = async (opportunity) => {
    const id = opportunity?.slug || opportunity?.id;
    if (!id) return;
    setNotice("");
    setActionId(`opportunity-${id}`);
    try {
      await applyExaSkillsOpportunity({
        apiBaseUrl,
        token,
        opportunity: id,
        body: { cover_note: "I am interested in this ExaSkills opportunity." },
      });
      setNotice("Opportunity application submitted.");
      await loadHome();
    } catch (err) {
      setNotice(err?.message || "Opportunity application could not be submitted.");
    } finally {
      setActionId(null);
    }
  };

  return (
    <main className="min-h-screen bg-[#080A0F] text-white app-shell">
      <div className="mx-auto w-full max-w-7xl px-3 pb-8 pt-4 sm:px-5 lg:px-8">
        <header className="rounded-2xl border border-white/10 bg-[#0D1118] p-4 shadow-xl sm:p-5">
          <div className="flex items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-3">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#D4AF37]/35 bg-[#D4AF37]/10">
                <img src={Image.edu} alt="ExaSkills" className="h-7 w-7 object-contain" />
              </div>
              <div className="min-w-0">
                <p className="text-xs uppercase tracking-[0.24em] text-[#D4AF37]/80">ExaEarn</p>
                <h1 className="truncate font-['Sora'] text-2xl font-semibold sm:text-3xl">ExaSkills</h1>
              </div>
            </div>
            {onBack ? (
              <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2 px-3 py-2 text-xs sm:text-sm">
                <ArrowLeft className="h-4 w-4" /> Back
              </button>
            ) : null}
          </div>

          <div className="mt-5 grid gap-5 lg:grid-cols-[1.12fr_0.88fr] lg:items-end">
            <div>
              <p className="inline-flex items-center gap-2 rounded-full border border-[#D4AF37]/35 bg-[#D4AF37]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#D4AF37]">
                <Sparkles className="h-3.5 w-3.5" /> Learn. Build. Prove. Earn.
              </p>
              <h2 className="mt-3 max-w-3xl font-['Sora'] text-3xl font-semibold leading-tight text-[#F8F8F8] sm:text-5xl">
                Learn skills. Prove what you can do. Get paid.
              </h2>
              <p className="mt-3 max-w-3xl text-sm leading-relaxed text-white/70 sm:text-base">
                Develop real-world skills, build your portfolio, earn verified credentials and unlock paid opportunities inside ExaEarn.
              </p>
              <div className="mt-5 flex flex-wrap gap-2">
                <button type="button" onClick={onOpenStartLearning} className="btn-gold inline-flex items-center gap-2">Explore Skills <ArrowRight className="h-4 w-4" /></button>
                <button type="button" onClick={onOpenApplyScholarship} className="btn-outline">Find Opportunities</button>
                <button type="button" onClick={onOpenBecomeEducator} className="btn-outline">Start Teaching</button>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-2">
              <Metric label="Published Programs" value={data.summary?.published_courses ?? 0} />
              <Metric label="Active Learners" value={data.summary?.active_learners ?? 0} />
              <Metric label="Open Challenges" value={data.summary?.open_challenges ?? 0} />
              <Metric label="Opportunities" value={data.summary?.open_opportunities ?? 0} />
            </div>
          </div>
        </header>

        {notice ? <p className="mt-4 rounded-xl border border-[#D4AF37]/30 bg-[#D4AF37]/10 px-4 py-3 text-sm text-[#F4D03F]">{notice}</p> : null}
        {error ? <ErrorState message={error} onRetry={loadHome} /> : null}

        {loading ? <LoadingGrid /> : (
          <div className="mt-5 grid gap-5 xl:grid-cols-[1fr_360px]">
            <div className="min-w-0 space-y-5">
              <section className="rounded-2xl border border-white/10 bg-[#0D1118] p-4 sm:p-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-[#D4AF37]/80">Marketplace</p>
                    <h2 className="font-['Sora'] text-xl font-semibold">Explore Skills</h2>
                  </div>
                  <label className="relative block w-full sm:max-w-xs">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/40" />
                    <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search programs" className="w-full rounded-xl border border-white/10 bg-[#080A0F] py-2.5 pl-9 pr-3 text-sm text-white outline-none focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/20" />
                  </label>
                </div>
                <div className="mt-4 flex gap-2 overflow-x-auto pb-1">
                  {categories.length ? categories.map((category) => (
                    <button key={category.id} type="button" className="shrink-0 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5 text-xs font-semibold text-white/75 hover:border-[#D4AF37]/50 hover:text-[#D4AF37]">{category.name}</button>
                  )) : <CompactEmpty text="No skill categories have been enabled yet." />}
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                  {filteredCourses.length ? filteredCourses.map((course) => (
                    <CourseCard key={courseKey(course)} course={course} enrolling={enrollingId === courseKey(course)} onEnroll={() => handleEnroll(course)} onView={() => onOpenCourseUpload(courseKey(course))} />
                  )) : <LargeEmpty title="No programs available" text="Published ExaSkills courses will appear here when admins or approved instructors enable them." />}
                </div>
              </section>

              <section className="grid gap-5 lg:grid-cols-2">
                <Panel title="Skill Challenges" eyebrow="Build" icon={Trophy}>
                  {challenges.length ? challenges.map((challenge) => <ChallengeRow key={challenge.id} challenge={challenge} busy={actionId === `challenge-${challenge.slug || challenge.id}`} onSubmit={() => handleChallengeSubmit(challenge)} />) : <CompactEmpty text="No sponsored challenges are open right now." />}
                </Panel>
                <Panel title="Paid Opportunities" eyebrow="Earn" icon={BriefcaseBusiness}>
                  {opportunities.length ? opportunities.map((opportunity) => <OpportunityRow key={opportunity.id} opportunity={opportunity} busy={actionId === `opportunity-${opportunity.slug || opportunity.id}`} onApply={() => handleOpportunityApply(opportunity)} />) : <CompactEmpty text="No paid opportunities are currently published." />}
                </Panel>
              </section>
            </div>

            <aside className="space-y-5 xl:sticky xl:top-4 xl:self-start">
              <Panel title="Continue Learning" eyebrow="My ExaSkills" icon={BookOpen}>
                {continueLearning.length ? continueLearning.map((item) => <ProgressCard key={item.id} item={item} />) : <CompactEmpty text="Enroll in a program to start tracking progress." />}
              </Panel>
              <Panel title="Verified Proof" eyebrow="Prove" icon={BadgeCheck}>
                <div className="grid gap-2 text-sm text-white/75">
                  <ProofItem text="Credentials require completion evidence, assessments or project verification." />
                  <ProofItem text="Public verification can be enabled without exposing private account data." />
                  <ProofItem text="Portfolio and opportunity workflows are separated from wallet secrets." />
                </div>
              </Panel>
              <Panel title="For Businesses" eyebrow="Hire" icon={Building2}>
                <p className="text-sm text-white/65">Sponsor practical challenges, post opportunities, and discover verified ExaSkills talent after business approval.</p>
                <button type="button" className="btn-outline mt-4 w-full text-sm">Business Portal</button>
              </Panel>
              <Panel title="Instructor Hub" eyebrow="Teach" icon={GraduationCap}>
                <p className="text-sm text-white/65">Approved experts can publish paid programs, track students, and earn through the ExaSkills marketplace.</p>
                <div className="mt-4 grid gap-2">
                  <button type="button" onClick={onOpenBecomeEducator} className="btn-gold text-sm">Apply to Teach</button>
                  <button type="button" onClick={onOpenInstructorDashboard} className="btn-outline text-sm">Instructor Dashboard</button>
                </div>
              </Panel>
            </aside>
          </div>
        )}
      </div>
    </main>
  );
}

function Metric({ label, value }) {
  return <article className="rounded-xl border border-white/10 bg-[#080A0F] p-3"><p className="text-[11px] uppercase tracking-[0.14em] text-white/45">{label}</p><p className="mt-1 text-xl font-semibold text-[#D4AF37]">{Number(value || 0).toLocaleString()}</p></article>;
}

function Panel({ title, eyebrow, icon: Icon, children }) {
  return <section className="rounded-2xl border border-white/10 bg-[#0D1118] p-4 sm:p-5"><div className="mb-4 flex items-center gap-2"><span className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#D4AF37]/30 bg-[#D4AF37]/10 text-[#D4AF37]"><Icon className="h-4 w-4" /></span><div><p className="text-[11px] uppercase tracking-[0.18em] text-[#D4AF37]/75">{eyebrow}</p><h2 className="font-['Sora'] text-lg font-semibold">{title}</h2></div></div><div className="space-y-3">{children}</div></section>;
}

function CourseCard({ course, enrolling, onEnroll, onView }) {
  return <article className="flex min-h-[260px] flex-col rounded-xl border border-white/10 bg-[#080A0F] p-3 transition hover:border-[#D4AF37]/45"><div className="relative h-32 overflow-hidden rounded-lg border border-white/10 bg-white/[0.03]">{course.thumbnail_url ? <img src={course.thumbnail_url} alt={course.title} className="h-full w-full object-cover" /> : <img src={Image.edu} alt="ExaSkills program" className="h-full w-full object-cover opacity-70" />}<span className="absolute right-2 top-2 rounded-full border border-white/15 bg-black/60 px-2 py-0.5 text-[11px] text-white/80">{course.difficulty || "All levels"}</span></div><div className="mt-3 flex-1"><p className="text-sm font-semibold text-white">{course.title}</p><p className="mt-1 line-clamp-2 text-xs leading-relaxed text-white/55">{course.description}</p><div className="mt-3 flex flex-wrap gap-2 text-[11px] text-white/60"><span className="rounded-full bg-white/[0.05] px-2 py-1">{course.category?.name || "General"}</span><span className="rounded-full bg-white/[0.05] px-2 py-1"><Clock3 className="mr-1 inline h-3 w-3" />{course.duration || 0} min</span><span className="rounded-full bg-white/[0.05] px-2 py-1">{course.enrollments_count || 0} learners</span></div></div><div className="mt-4 flex items-center justify-between gap-3"><div><p className="text-[11px] text-white/45">Price</p><p className="text-sm font-semibold text-[#D4AF37]">{formatMoney(course.price, course.settlement_asset)}</p></div><div className="flex gap-2"><button type="button" onClick={onView} className="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-white/75 hover:border-[#D4AF37]/50">View</button><button type="button" onClick={onEnroll} disabled={enrolling} className="rounded-lg bg-[#D4AF37] px-3 py-2 text-xs font-semibold text-black disabled:opacity-60">{enrolling ? <Loader2 className="h-4 w-4 animate-spin" /> : Number(course.price || 0) > 0 ? "Buy" : "Enroll"}</button></div></div></article>;
}

function ChallengeRow({ challenge, busy, onSubmit }) {
  return <article className="rounded-xl border border-white/10 bg-[#080A0F] p-3"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate text-sm font-semibold text-white">{challenge.title}</p><p className="mt-1 text-xs text-white/55">{challenge.sponsor_name || "ExaSkills Sponsor"}</p></div><span className="shrink-0 rounded-full bg-[#D4AF37]/10 px-2 py-1 text-xs font-semibold text-[#D4AF37]">{formatMoney(challenge.reward_amount, challenge.reward_asset)}</span></div><p className="mt-2 line-clamp-2 text-xs leading-relaxed text-white/55">{challenge.description}</p><button type="button" onClick={onSubmit} disabled={busy} className="mt-3 w-full rounded-lg border border-[#D4AF37]/35 px-3 py-2 text-xs font-semibold text-[#D4AF37] disabled:opacity-60">{busy ? "Saving..." : "Join / Submit"}</button></article>;
}

function OpportunityRow({ opportunity, busy, onApply }) {
  return <article className="rounded-xl border border-white/10 bg-[#080A0F] p-3"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate text-sm font-semibold text-white">{opportunity.title}</p><p className="mt-1 text-xs text-white/55">{opportunity.company_name}</p></div><span className="shrink-0 rounded-full border border-white/10 px-2 py-1 text-[11px] text-white/65">{opportunity.type}</span></div><p className="mt-2 text-xs text-[#D4AF37]">{opportunity.compensation_label || "Compensation disclosed in opportunity"}</p><button type="button" onClick={onApply} disabled={busy} className="mt-3 w-full rounded-lg border border-[#D4AF37]/35 px-3 py-2 text-xs font-semibold text-[#D4AF37] disabled:opacity-60">{busy ? "Submitting..." : "Apply"}</button></article>;
}

function ProgressCard({ item }) {
  const progress = Math.min(100, Number(item.progress_percentage || 0));
  return <div className="rounded-xl border border-white/10 bg-[#080A0F] p-3"><p className="text-sm font-semibold text-white">{item.course?.title || "Course"}</p><p className="mt-1 text-xs text-white/55">Progress: {progress.toFixed(0)}%</p><div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10"><span className="block h-full rounded-full bg-[#D4AF37]" style={{ width: `${progress}%` }} /></div></div>;
}

function ProofItem({ text }) {
  return <p className="flex gap-2"><CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#D4AF37]" />{text}</p>;
}

function CompactEmpty({ text }) {
  return <p className="rounded-xl border border-dashed border-white/10 bg-white/[0.02] px-3 py-3 text-sm text-white/50">{text}</p>;
}

function LargeEmpty({ title, text }) {
  return <div className="rounded-xl border border-dashed border-white/10 bg-white/[0.02] p-5 md:col-span-2 2xl:col-span-3"><ShieldCheck className="h-8 w-8 text-[#D4AF37]" /><h3 className="mt-3 text-base font-semibold text-white">{title}</h3><p className="mt-1 max-w-xl text-sm text-white/55">{text}</p></div>;
}

function LoadingGrid() {
  return <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">{Array.from({ length: 6 }).map((_, index) => <div key={index} className="h-48 animate-pulse rounded-2xl border border-white/10 bg-white/[0.04]" />)}</div>;
}

function ErrorState({ message, onRetry }) {
  return <div className="mt-4 rounded-2xl border border-red-400/25 bg-red-500/10 p-4"><p className="text-sm font-semibold text-red-100">ExaSkills could not load</p><p className="mt-1 text-sm text-red-100/75">{message}</p><button type="button" onClick={onRetry} className="mt-3 rounded-lg border border-red-200/30 px-3 py-2 text-xs font-semibold text-red-100">Retry</button></div>;
}

export default ExaSkills;

