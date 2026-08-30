import { createContext, useContext, useEffect, useMemo, useState, type FormEvent, type ReactNode } from "react";
import { ArrowLeft, ArrowRight, CheckCircle2, Eye, EyeOff, KeyRound, Laptop, LoaderCircle, LogOut, ShieldCheck, Terminal, Trash2 } from "lucide-react";
import { DeveloperWorkspaceConsole } from "./workspaceConsole";

const apiBaseUrl = (import.meta.env.VITE_API_URL || "https://api.exaearn.com").replace(/\/$/, "");
const mainAppUrl = (import.meta.env.VITE_APP_URL || "https://app.exaearn.com").replace(/\/$/, "");

type DeveloperSession = {
  user: { id: number; name: string; unique_user_id: string; email_verified: boolean; two_factor_enabled: boolean };
  developer_profile: { id: number; status: string; onboarding_status: string };
};
type AuthContextValue = { session: DeveloperSession | null; loading: boolean; refresh: () => Promise<DeveloperSession | null>; signOut: () => Promise<void> };
const AuthContext = createContext<AuthContextValue | null>(null);

async function jsonRequest(path: string, init: RequestInit = {}) {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...init,
    credentials: "include",
    headers: { Accept: "application/json", "Content-Type": "application/json", ...init.headers },
  });
  const payload = await response.json().catch(() => ({}));
  if (response.status === 401 && window.location.pathname.startsWith("/developers/console")) {
    const returnTo = safeReturnTo(window.location.pathname);
    window.location.assign(`/developers/login?expired=1&returnTo=${encodeURIComponent(returnTo)}`);
  }
  return { response, payload };
}

export function DeveloperAuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<DeveloperSession | null>(null);
  const [loading, setLoading] = useState(true);
  const refresh = async () => {
    try {
      const { response, payload } = await jsonRequest("/api/developer/session", { method: "POST", body: "{}" });
      const next = response.ok ? payload.data as DeveloperSession : null;
      setSession(next);
      return next;
    } catch { setSession(null); return null; }
    finally { setLoading(false); }
  };
  useEffect(() => { void refresh(); }, []);
  const signOut = async () => { try { await jsonRequest("/api/logout", { method: "POST", body: "{}" }); } finally { setSession(null); window.location.assign("/developers"); } };
  const value = useMemo(() => ({ session, loading, refresh, signOut }), [session, loading]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useDeveloperAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error("DeveloperAuthProvider is missing.");
  return value;
}

export function safeReturnTo(raw: string | null, fallback = "/developers/console") {
  if (!raw || !raw.startsWith("/developers/") || raw.startsWith("//") || raw.includes(":") || raw.includes("\\")) return fallback;
  return raw.startsWith("/developers/console") || raw.startsWith("/developers/onboarding") ? raw : fallback;
}

export function DeveloperLogin() {
  const { session, refresh } = useDeveloperAuth();
  const [email, setEmail] = useState(""); const [password, setPassword] = useState(""); const [code, setCode] = useState("");
  const [showPassword, setShowPassword] = useState(false); const [requires2FA, setRequires2FA] = useState(false);
  const [busy, setBusy] = useState(false); const [error, setError] = useState("");
  const returnTo = safeReturnTo(new URLSearchParams(window.location.search).get("returnTo"));
  useEffect(() => { if (session) window.location.replace(returnTo); }, [session, returnTo]);
  const submit = async (event: FormEvent) => {
    event.preventDefault(); if (busy) return; setBusy(true); setError("");
    try {
      const { response, payload } = await jsonRequest("/api/login", { method: "POST", body: JSON.stringify({ email, password, two_factor_code: requires2FA ? code : undefined, device_name: "ExaEarn Developers" }) });
      if (payload.code === "2FA_REQUIRED") { setRequires2FA(true); setCode(""); return; }
      if (!response.ok) {
        const messages: Record<string, string> = { INVALID_CREDENTIALS: "Incorrect email or password.", INVALID_2FA_CODE: "The authentication code is invalid or expired.", ACCOUNT_LOCKED: "Your account is locked. Use account recovery or contact support.", ACCOUNT_DISABLED: "This account cannot sign in. Contact ExaEarn Support.", EMAIL_UNVERIFIED: "Verify your email to continue." };
        setError(messages[payload.code] || (response.status === 429 ? "Too many attempts. Please wait and try again." : "We could not sign you in. Please try again.")); return;
      }
      const resolved = await refresh();
      if (!resolved) { setError("Your session could not be established. Please sign in again."); return; }
      const destination = resolved.developer_profile.onboarding_status === "completed" ? returnTo : "/developers/onboarding";
      window.location.replace(destination);
    } catch { setError("Unable to reach ExaEarn. Check your connection and try again."); }
    finally { setBusy(false); }
  };
  return <main className="developer-auth-page"><a className="auth-back" href="/docs"><ArrowLeft size={16} /> Back to Developer Docs</a><div className="auth-layout"><section className="auth-message"><span className="auth-kicker">EXAEARN DEVELOPERS</span><h1>Build securely on ExaEarn.</h1><p>Access sandbox environments, API keys, WebSockets, webhooks and production integrations from one Developer Console.</p><div className="auth-trust"><span><Terminal size={17} /> Isolated sandbox</span><span><KeyRound size={17} /> Scoped API keys</span><span><ShieldCheck size={17} /> Canonical ExaEarn security</span></div></section><section className="login-panel" aria-labelledby="login-title"><div><p className="eyebrow">One ExaEarn identity</p><h2 id="login-title">{requires2FA ? "Verify it’s you" : "Sign in to Developers"}</h2><p>{requires2FA ? "Enter the six-digit code from your authenticator app." : "Use your existing ExaEarn account. You do not need separate Developer credentials."}</p></div><form onSubmit={submit}>{!requires2FA ? <><label>Email<input type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} required autoFocus /></label><label>Password<span className="password-field"><input type={showPassword ? "text" : "password"} autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required /><button type="button" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? "Hide password" : "Show password"}>{showPassword ? <EyeOff size={17} /> : <Eye size={17} />}</button></span></label><div className="form-links"><span>Protected by ExaEarn account security</span><a href={`${mainAppUrl}/forgot-password`}>Forgot password?</a></div></> : <label>Authenticator code<input className="otp-input" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} autoComplete="one-time-code" value={code} onChange={(event) => setCode(event.target.value.replace(/\D/g, ""))} required autoFocus /></label>}{error && <div className="auth-error" role="alert">{error}</div>}<button className="auth-submit" disabled={busy || (requires2FA && code.length !== 6)}>{busy ? <><LoaderCircle className="spin" size={17} /> Signing in...</> : <>Continue <ArrowRight size={17} /></>}</button>{requires2FA && <button className="auth-secondary" type="button" onClick={() => { setRequires2FA(false); setCode(""); setError(""); }}>Use a different account</button>}</form>{!requires2FA && <p className="signup-prompt">New to ExaEarn? <a href="/developers/signup">Create account</a></p>}</section></div></main>;
}

export function DeveloperForgotPassword() {
  const [email,setEmail]=useState(""); const [busy,setBusy]=useState(false); const [sent,setSent]=useState(false); const [error,setError]=useState("");
  const submit=async(event:FormEvent)=>{event.preventDefault();setBusy(true);setError("");try{await jsonRequest("/api/forgot-password",{method:"POST",body:JSON.stringify({email})});setSent(true)}catch{setError("Unable to reach ExaEarn. Try again shortly.")}finally{setBusy(false)}};
  return <AuthRecoveryShell title="Reset your password" detail="Enter your canonical ExaEarn account email.">{sent?<div className="recovery-complete"><CheckCircle2/><h3>Check your email</h3><p>If an account exists for that email, reset instructions have been sent.</p><a href="/developers/login">Back to sign in</a></div>:<form onSubmit={submit}><label>Email<input type="email" autoComplete="email" value={email} onChange={e=>setEmail(e.target.value)} required autoFocus/></label>{error&&<div className="auth-error" role="alert">{error}</div>}<button className="auth-submit" disabled={busy}>{busy?"Sending...":"Continue"}</button><a className="auth-text-link" href="/developers/login">Back to sign in</a></form>}</AuthRecoveryShell>;
}

export function DeveloperResetPassword() {
  const params=new URLSearchParams(window.location.search); const token=params.get("token")||""; const email=params.get("email")||"";
  const [password,setPassword]=useState(""); const [confirmation,setConfirmation]=useState(""); const [busy,setBusy]=useState(false); const [done,setDone]=useState(false); const [error,setError]=useState("");
  const valid=password.length>=10&&/[a-z]/.test(password)&&/[A-Z]/.test(password)&&/\d/.test(password)&&/[^\w\s]/.test(password);
  const submit=async(event:FormEvent)=>{event.preventDefault();if(!valid||password!==confirmation){setError("Use 10+ characters with uppercase, lowercase, a number and symbol; both entries must match.");return}setBusy(true);setError("");try{const {response,payload}=await jsonRequest("/api/reset-password",{method:"POST",body:JSON.stringify({token,email,password,password_confirmation:confirmation})});if(!response.ok){setError(payload.message||"This reset link is invalid or has expired.");return}setDone(true)}catch{setError("Unable to reach ExaEarn. Try again shortly.")}finally{setBusy(false)}};
  return <AuthRecoveryShell title="Choose a new password" detail="Your new password applies to your one ExaEarn identity.">{done?<div className="recovery-complete"><CheckCircle2/><h3>Password updated</h3><p>Other sessions were signed out to protect your account.</p><a href="/developers/login">Sign in to Developers</a></div>:!token||!email?<div className="auth-error" role="alert">This reset link is incomplete. Request a new one.</div>:<form onSubmit={submit}><label>New password<input type="password" autoComplete="new-password" value={password} onChange={e=>setPassword(e.target.value)} required autoFocus/><small>10+ characters with uppercase, lowercase, number and symbol</small></label><label>Confirm password<input type="password" autoComplete="new-password" value={confirmation} onChange={e=>setConfirmation(e.target.value)} required/></label>{error&&<div className="auth-error" role="alert">{error}</div>}<button className="auth-submit" disabled={busy}>{busy?"Updating...":"Update password"}</button></form>}</AuthRecoveryShell>;
}

function AuthRecoveryShell({title,detail,children}:{title:string;detail:string;children:ReactNode}) { return <main className="developer-auth-page recovery-page"><a className="auth-back" href="/developers/login"><ArrowLeft size={16}/> Back to sign in</a><section className="login-panel"><div className="security-mark"><ShieldCheck/></div><p className="eyebrow">EXAEARN DEVELOPERS</p><h1>{title}</h1><p>{detail}</p>{children}</section></main> }

export function DeveloperSignup() {
  const { refresh } = useDeveloperAuth();
  const [name,setName]=useState(""); const [email,setEmail]=useState(""); const [password,setPassword]=useState(""); const [confirmation,setConfirmation]=useState("");
  const [show,setShow]=useState(false); const [accepted,setAccepted]=useState(false); const [busy,setBusy]=useState(false); const [error,setError]=useState("");
  const validPassword = password.length >= 10 && /[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password) && /[^\w\s]/.test(password);
  const submit=async(event:FormEvent)=>{event.preventDefault();if(busy)return;setError("");if(!validPassword){setError("Use at least 10 characters with uppercase, lowercase, a number and a symbol.");return}if(password!==confirmation){setError("Passwords do not match.");return}setBusy(true);try{const {response,payload}=await jsonRequest("/api/register",{method:"POST",body:JSON.stringify({name,email,password,password_confirmation:confirmation,registration_context:"developers"})});if(!response.ok){setError(payload.code==="ACCOUNT_EXISTS"?"An ExaEarn account may already use this email. Sign in to continue with Developers.":payload.message||"We could not create your account.");return}await refresh();window.location.assign(`/developers/verify-email?email=${encodeURIComponent(email)}`)}catch{setError("Unable to reach ExaEarn. Check your connection and try again.")}finally{setBusy(false)}};
  return <main className="developer-auth-page"><a className="auth-back" href="/docs"><ArrowLeft size={16}/> Back to Developer Docs</a><div className="auth-layout signup-layout"><section className="auth-message"><span className="auth-kicker">EXAEARN DEVELOPERS</span><h1>Build. Test. Integrate.</h1><p>Create one ExaEarn identity for Sandbox APIs, market data, WebSockets, SDKs and webhooks.</p><div className="auth-trust"><span><Terminal size={17}/> Exchange APIs</span><span><KeyRound size={17}/> Sandbox first</span><span><ShieldCheck size={17}/> Production separately approved</span></div></section><section className="login-panel"><div><p className="eyebrow">Developer signup</p><h2>Create your account</h2><p>Build, test and integrate with ExaEarn APIs.</p></div><form onSubmit={submit}><label>Full name<input autoComplete="name" value={name} onChange={e=>setName(e.target.value)} required autoFocus/></label><label>Email address<input type="email" autoComplete="email" value={email} onChange={e=>setEmail(e.target.value)} required/></label><label>Password<span className="password-field"><input type={show?"text":"password"} autoComplete="new-password" value={password} onChange={e=>setPassword(e.target.value)} required/><button type="button" onClick={()=>setShow(v=>!v)} aria-label={show?"Hide password":"Show password"}>{show?<EyeOff size={17}/>:<Eye size={17}/>}</button></span><small className={password&&validPassword?"valid-requirement":""}>10+ characters with uppercase, lowercase, number and symbol</small></label><label>Confirm password<input type={show?"text":"password"} autoComplete="new-password" value={confirmation} onChange={e=>setConfirmation(e.target.value)} required/></label><label className="terms-check"><input type="checkbox" checked={accepted} onChange={e=>setAccepted(e.target.checked)} required/><span>I agree to the ExaEarn Terms and Developer Terms.</span></label>{error&&<div className="auth-error" role="alert">{error}</div>}<button className="auth-submit" disabled={busy||!accepted}>{busy?<><LoaderCircle className="spin" size={17}/> Creating account...</>:<>Create account <ArrowRight size={17}/></>}</button></form><p className="signup-prompt">Already have an ExaEarn account? <a href="/developers/login">Sign in</a></p></section></div></main>;
}

export function DeveloperVerifyEmail() {
  const {session,loading}=useDeveloperAuth(); const [sent,setSent]=useState(false); const [busy,setBusy]=useState(false); const [error,setError]=useState("");
  const raw=new URLSearchParams(window.location.search).get("email")||""; const masked=raw.replace(/^(.)(.*)(@.*)$/,(...parts)=>`${parts[1]}***${parts[3]}`);
  useEffect(()=>{if(!loading&&session?.user.email_verified)window.location.replace("/developers/onboarding")},[loading,session]);
  const resend=async()=>{setBusy(true);setError("");try{const {response}=await jsonRequest("/api/email/verification-notification",{method:"POST",body:"{}"});if(!response.ok)throw new Error();setSent(true)}catch{setError("We could not resend the email. Wait a moment and try again.")}finally{setBusy(false)}};
  return <main className="console-state verify-state"><ShieldCheck/><p className="eyebrow">Account security</p><h1>Verify your email</h1><p>We sent a verification link{masked?<> to <strong>{masked}</strong></>:" to your ExaEarn email"}. Open it in this browser to continue to Developer onboarding.</p>{sent&&<div className="verification-success">Verification email sent.</div>}{error&&<div className="auth-error" role="alert">{error}</div>}<button className="auth-submit" disabled={busy} onClick={()=>void resend()}>{busy?"Sending...":"Resend verification email"}</button><a href="/developers/login">Back to sign in</a></main>;
}

type SecuritySession = { id:number; name:string; last_used_at:string|null; created_at:string };
type SecurityDevice = { id:number; device_name:string|null; browser:string|null; last_active:string|null };

function DeveloperSecuritySettings() {
  const {session}=useDeveloperAuth(); const [sessions,setSessions]=useState<SecuritySession[]>([]); const [devices,setDevices]=useState<SecurityDevice[]>([]); const [loading,setLoading]=useState(true); const [password,setPassword]=useState(""); const [code,setCode]=useState(""); const [confirmed,setConfirmed]=useState(false); const [error,setError]=useState("");
  const load=async()=>{setLoading(true);const {response,payload}=await jsonRequest("/api/auth/sessions");if(response.ok){setSessions(payload.data.sessions||[]);setDevices(payload.data.devices||[])}setLoading(false)};
  useEffect(()=>{void load()},[]);
  const confirm=async(event:FormEvent)=>{event.preventDefault();setError("");const {response}=await jsonRequest("/api/auth/reauthenticate",{method:"POST",body:JSON.stringify({password,two_factor_code:session?.user.two_factor_enabled?code:undefined})});if(response.ok){setConfirmed(true);setPassword("");setCode("")}else setError("Security confirmation failed.")};
  const revoke=async(id:number)=>{if(!confirmed){setError("Confirm your identity before revoking a session.");return}await jsonRequest(`/api/auth/sessions/${id}`,{method:"DELETE"});void load()};
  const logoutAll=async()=>{if(!confirmed){setError("Confirm your identity before signing out all devices.");return}if(!window.confirm("Sign out every ExaEarn session and device?"))return;await jsonRequest("/api/auth/logout-all",{method:"POST",body:"{}"});window.location.assign("/developers/login")};
  return <main className="security-settings"><header><p className="eyebrow">Developer Console · Settings</p><h1>Security</h1><p>Canonical ExaEarn identity controls protect both the exchange and Developer Portal.</p></header><section className="security-grid"><article><ShieldCheck/><div><h2>Two-factor authentication</h2><p>{session?.user.two_factor_enabled?"Authenticator protection is enabled.":"Two-factor authentication is not enabled."}</p></div><span className={session?.user.two_factor_enabled?"security-on":"security-off"}>{session?.user.two_factor_enabled?"Enabled":"Not enabled"}</span></article><article><KeyRound/><div><h2>Password</h2><p>Password changes and recovery apply to your one ExaEarn identity.</p></div><a href="/developers/forgot-password">Reset password</a></article></section><section className="session-panel"><div><h2>Sessions and devices</h2><p>Review recent device activity and revoke API-backed sessions.</p></div>{loading?<p>Loading secure sessions...</p>:<div className="session-list">{sessions.map(item=><article key={item.id}><Laptop/><div><strong>{item.name||"ExaEarn session"}</strong><small>Created {new Date(item.created_at).toLocaleString()} · {item.last_used_at?`Last used ${new Date(item.last_used_at).toLocaleString()}`:"Not used yet"}</small></div><button onClick={()=>void revoke(item.id)} aria-label={`Revoke ${item.name}`}><Trash2 size={16}/></button></article>)}{devices.map(item=><article key={`device-${item.id}`}><Laptop/><div><strong>{item.device_name||"Known device"}</strong><small>{item.browser||"Browser details unavailable"} · {item.last_active?new Date(item.last_active).toLocaleString():"Activity unavailable"}</small></div></article>)}</div>}</section><section className="reauth-panel"><div><h2>Confirm sensitive actions</h2><p>{confirmed?"Identity confirmed for the configured security window.":"Enter your password and required authenticator code before revoking sessions or changing high-risk credentials."}</p></div>{!confirmed&&<form onSubmit={confirm}><input type="password" autoComplete="current-password" placeholder="Password" value={password} onChange={e=>setPassword(e.target.value)} required/>{session?.user.two_factor_enabled&&<input className="otp-input" inputMode="numeric" maxLength={6} placeholder="Authenticator code" value={code} onChange={e=>setCode(e.target.value.replace(/\D/g,""))} required/>}<button>Confirm identity</button></form>}{error&&<div className="auth-error" role="alert">{error}</div>}<button className="danger-button" onClick={()=>void logoutAll()}>Sign out all devices</button></section></main>;
}

export function ProtectedDeveloperPage({ onboarding = false, security = false, team = false, apiKeys = false, productionAccess = false, invitation = false }: { onboarding?: boolean; security?: boolean; team?: boolean; apiKeys?: boolean; productionAccess?: boolean; invitation?: boolean }) {
  const { session, loading, signOut } = useDeveloperAuth();
  useEffect(() => { if (!loading && !session) window.location.replace(`/developers/login?returnTo=${encodeURIComponent(window.location.pathname)}`); }, [loading, session]);
  if (loading || !session) return <main className="console-state"><LoaderCircle className="spin" /><p>Checking your secure session...</p></main>;
  if (!session.user.email_verified) return <main className="console-state"><ShieldCheck /><h1>Verify your email</h1><p>Confirm your ExaEarn email before creating Developer credentials.</p><a className="auth-submit" href={`${mainAppUrl}/settings/security`}>Open account security</a></main>;
  if (security) return <DeveloperSecuritySettings />;
  if (onboarding && session.developer_profile.onboarding_status !== "completed") return <DeveloperOnboarding />;
  if (!onboarding) return <DeveloperWorkspaceConsole team={team} apiKeys={apiKeys} productionAccess={productionAccess} invitation={invitation} />;
  return <main className="developer-console"><header><div><p className="eyebrow">Developer Console</p><h1>{onboarding ? "Set up your Developer workspace" : `Welcome, ${session.user.name}`}</h1><p>{onboarding ? "Start in Sandbox. Production access remains separately verified and approved." : "Manage isolated projects and developer integrations through your canonical ExaEarn account."}</p></div><button onClick={() => void signOut()}><LogOut size={16} /> Sign out</button></header><section className="console-grid"><article><Terminal /><h2>{onboarding ? "Create your first Sandbox project" : "Projects"}</h2><p>{onboarding ? "Sandbox credentials cannot access production funds, orders or positions." : "Open your projects, credentials and request logs."}</p><a href={`${apiBaseUrl}/api/developer/projects`}>{onboarding ? "Continue setup" : "View projects"} <ArrowRight size={15} /></a></article><article><ShieldCheck /><h2>One secure identity</h2><p>Account security, devices and 2FA are managed by your main ExaEarn account.</p><a href={`${mainAppUrl}/settings/security`}>Account security <ArrowRight size={15} /></a></article><article><CheckCircle2 /><h2>Production stays controlled</h2><p>Developer login and Sandbox do not require KYC. Production financial permissions have separate eligibility gates.</p><a href="/docs/environments">Environment policy <ArrowRight size={15} /></a></article></section></main>;
}

function DeveloperOnboarding() {
  const {refresh}=useDeveloperAuth(); const [useCase,setUseCase]=useState("trading_application"); const [type,setType]=useState("individual"); const [organization,setOrganization]=useState(""); const [project,setProject]=useState(""); const [accepted,setAccepted]=useState(false); const [busy,setBusy]=useState(false); const [error,setError]=useState("");
  const submit=async(event:FormEvent)=>{event.preventDefault();setBusy(true);setError("");try{const {response,payload}=await jsonRequest("/api/developer/onboarding",{method:"POST",body:JSON.stringify({developer_type:type,use_case:useCase,organization_name:type==="organization"?organization:undefined,project_name:project,terms_accepted:accepted})});if(!response.ok){setError(payload.error?.message||payload.message||"We could not create your workspace.");return}await refresh();window.location.assign("/developers/console")}catch{setError("Unable to reach ExaEarn. Check your connection and try again.")}finally{setBusy(false)}};
  return <main className="onboarding-page"><header><p className="eyebrow">Sandbox first</p><h1>Welcome to ExaEarn Developers</h1><p>Create a focused workspace now. Production financial access remains disabled until a separate eligibility and approval process.</p></header><form className="onboarding-form" onSubmit={submit}><fieldset><legend>What are you building?</legend><select value={useCase} onChange={e=>setUseCase(e.target.value)}><option value="trading_application">Trading application</option><option value="trading_bot">Trading bot</option><option value="wallet_payments">Wallet / payments integration</option><option value="market_data">Market-data application</option><option value="fintech">Fintech application</option><option value="institutional">Institutional integration</option><option value="learning">Learning / testing</option><option value="other">Other</option></select></fieldset><fieldset><legend>How are you building?</legend><div className="segmented"><button type="button" className={type==="individual"?"active":""} onClick={()=>setType("individual")}>Individual developer</button><button type="button" className={type==="organization"?"active":""} onClick={()=>setType("organization")}>Company / organization</button></div></fieldset>{type==="organization"&&<label>Organization name<input value={organization} onChange={e=>setOrganization(e.target.value)} required/></label>}<label>First project name<input value={project} onChange={e=>setProject(e.target.value)} placeholder="My Trading App" required/><small>Environment: Sandbox</small></label><label className="terms-check"><input type="checkbox" checked={accepted} onChange={e=>setAccepted(e.target.checked)} required/><span>I accept the Developer Terms for this workspace.</span></label>{error&&<div className="auth-error" role="alert">{error}</div>}<button className="auth-submit" disabled={busy||!accepted}>{busy?<><LoaderCircle className="spin" size={17}/> Creating workspace...</>:<>Create Sandbox project <ArrowRight size={17}/></>}</button></form></main>;
}
