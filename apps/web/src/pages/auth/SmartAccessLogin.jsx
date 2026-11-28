import { useCallback, useEffect, useState } from "react";
import {
  AlertCircle,
  ArrowLeft,
  Delete,
  Bluetooth,
  CheckCircle2,
  ChevronRight,
  Fingerprint,
  KeyRound,
  LockKeyhole,
  RotateCw,
  ShieldCheck,
  Smartphone,
  Wifi,
  WifiOff,
} from "lucide-react";
import Image from "../../assets/Image";
import LanguageSwitcher from "../../components/language/LanguageSwitcher.jsx";
import { useAuth } from "../../context/AuthContext";
import { getApiBaseUrl } from "../../config/apiConfig.js";
import {
  passcodeIndicatorState,
  SMART_ACCESS_PASSCODE_LENGTH,
  visibleSmartAccessMethods,
} from "./smartAccessModel.js";

const methodCopy = {
  passkey: { title: "Passkey", detail: "Not connected to account sign-in", Icon: KeyRound },
  biometric: { title: "Biometrics", detail: "Not enrolled for ExaEarn account access", Icon: Fingerprint },
  "device-pin": { title: "Device PIN", detail: "Your device credential is never collected", Icon: Smartphone },
  password: { title: "Account password", detail: "Continue with your existing password", Icon: LockKeyhole },
};

const flowCopy = {
  "create-passcode": ["Create Passcode", "Choose a 7-digit ExaEarn Passcode. Secure enrollment requires verified account controls; this preview never stores the digits."],
  "confirm-passcode": ["Confirm Passcode", "Confirm the 7-digit passcode during a verified enrollment flow. This preview does not save credentials."],
  "change-passcode": ["Change Passcode", "Changing a passcode requires account reauthentication, throttling, and recovery support. No credential is changed here."],
  "forgot-passcode": ["Forgot Passcode?", "Recover Smart Access only after verifying your ExaEarn account. A local reset cannot authorize an account session."],
  "passkey-enrollment": ["Set up a Passkey", "Passkey enrollment is unavailable until an account-bound WebAuthn challenge, origin checks, verification, and recovery are implemented."],
  "biometric-enrollment": ["Biometric Access", "This browser cannot use biometrics as ExaEarn account sign-in in this build. No biometric prompt or authorization was performed."],
  "device-credential": ["Device Credential", "ExaEarn never asks you to type your device PIN. Native credential prompts require an enrolled account session and are unavailable in this web client."],
  "authentication-error": ["Passcode sign-in unavailable", "This account has no enrolled ExaEarn Passcode in the current build. No session was created."],
  "security-lockout": ["Security lockout", "A real lockout message must come from the authentication backend. This interface does not create or simulate one."],
};

function getConnectionPresentation(state) {
  if (state === "online") return { label: "Online", Icon: Wifi, color: "text-emerald-300", border: "border-emerald-300/25" };
  if (state === "offline") return { label: "Offline", Icon: WifiOff, color: "text-amber-300", border: "border-amber-300/25" };
  if (state === "limited") return { label: "Limited", Icon: AlertCircle, color: "text-amber-300", border: "border-amber-300/25" };
  return { label: "Reconnecting", Icon: RotateCw, color: "text-sky-300", border: "border-sky-300/25" };
}

function useConnectionState(apiBaseUrl) {
  const [state, setState] = useState("reconnecting");
  const checkConnection = useCallback(async () => {
    if (!apiBaseUrl) {
      setState("limited");
      return;
    }
    if (typeof navigator !== "undefined" && navigator.onLine === false) {
      setState("offline");
      return;
    }
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 4500);
    try {
      const response = await fetch(`${apiBaseUrl.replace(/\/+$/, "")}/up`, {
        method: "GET",
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      setState(response.status >= 500 ? "limited" : "online");
    } catch {
      setState("offline");
    } finally {
      window.clearTimeout(timeout);
    }
  }, [apiBaseUrl]);

  useEffect(() => {
    let active = true;
    const check = () => { if (active) void checkConnection(); };
    check();
    const interval = window.setInterval(check, 20_000);
    const reconnect = () => { setState("reconnecting"); check(); };
    const offline = () => setState("offline");
    window.addEventListener("online", reconnect);
    window.addEventListener("offline", offline);
    return () => {
      active = false;
      window.clearInterval(interval);
      window.removeEventListener("online", reconnect);
      window.removeEventListener("offline", offline);
    };
  }, [checkConnection]);

  return state;
}

function PasscodeDots({ count }) {
  const indicators = passcodeIndicatorState(count);
  return (
    <div className="flex justify-center gap-3" role="img" aria-label={`${count} of 7 passcode digits entered`}>
      {indicators.map((filled, index) => (
        <span key={index} className={`h-2.5 w-2.5 rounded-full border transition-colors motion-reduce:transition-none ${filled ? "border-emerald-300 bg-emerald-300" : "border-slate-500 bg-transparent"}`} />
      ))}
    </div>
  );
}

function SmartAccessFlow({ flow, offline, onBack, onPassword, onFlow }) {
  const [digits, setDigits] = useState("");
  const [notice, setNotice] = useState("");
  const [title, description] = flowCopy[flow];
  const collectsPasscode = ["create-passcode", "confirm-passcode", "change-passcode"].includes(flow);
  const appendDigit = (digit) => {
    setNotice("");
    if (digits.length >= SMART_ACCESS_PASSCODE_LENGTH - 1) {
      setDigits("");
      setNotice("Passcode enrollment is not connected to a secure backend. No passcode was saved or changed.");
      return;
    }
    setDigits((current) => `${current}${digit}`);
  };

  return (
    <section className="pt-3" aria-labelledby="smart-access-flow-title">
      <div className="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full border border-emerald-300/25 bg-emerald-300/[.08] text-emerald-300">
        {flow === "passkey-enrollment" ? <KeyRound size={25} /> : flow === "device-credential" ? <Smartphone size={25} /> : flow === "security-lockout" ? <ShieldCheck size={25} /> : <AlertCircle size={25} />}
      </div>
      <h1 id="smart-access-flow-title" className="text-center text-2xl font-semibold tracking-tight text-white">{title}</h1>
      <p className="mx-auto mt-2 max-w-sm text-center text-sm leading-6 text-slate-400">{description}</p>

      {collectsPasscode ? (
        <>
          <div className="mt-6"><PasscodeDots count={digits.length} /></div>
          <div className="mx-auto mt-4 grid max-w-[264px] grid-cols-3 gap-1">
            {["1", "2", "3", "4", "5", "6", "7", "8", "9", "", "0", "back"].map((key, index) => (
              <button
                key={`${key}-${index}`}
                type="button"
                disabled={!key}
                onClick={() => key === "back" ? setDigits((current) => current.slice(0, -1)) : key ? appendDigit(key) : undefined}
                aria-label={key === "back" ? "Delete last passcode digit" : key ? `Digit ${key}` : undefined}
                className="exa-focusable grid h-11 place-items-center rounded-xl text-xl text-slate-100 transition hover:bg-white/[.05] disabled:pointer-events-none motion-reduce:transition-none"
              >
                {key === "back" ? <Delete size={18} className="text-slate-300" /> : key}
              </button>
            ))}
          </div>
          {notice ? <p role="alert" className="mt-3 text-center text-xs leading-5 text-amber-200">{notice}</p> : null}
          <div className="mt-4 flex justify-center gap-4 text-xs">
            <button type="button" onClick={() => onFlow("confirm-passcode")} className="exa-focusable text-emerald-300 hover:text-emerald-200">Confirm flow</button>
            <button type="button" onClick={() => onFlow("change-passcode")} className="exa-focusable text-emerald-300 hover:text-emerald-200">Change flow</button>
            <button type="button" onClick={() => onFlow("forgot-passcode")} className="exa-focusable text-emerald-300 hover:text-emerald-200">Forgot</button>
          </div>
        </>
      ) : (
        <div className="mt-5 rounded-xl border border-sky-300/15 bg-sky-300/[.04] p-3 text-xs leading-5 text-slate-300">
          <span className="flex items-start gap-2"><CheckCircle2 size={16} className="mt-0.5 shrink-0 text-sky-300" />No authentication was performed and no credential was enrolled.</span>
          {(flow === "forgot-passcode" || flow === "authentication-error") && !offline ? <button type="button" onClick={onPassword} className="exa-focusable mt-3 min-h-10 w-full rounded-lg bg-emerald-700 px-3 font-semibold text-white hover:bg-emerald-600">Continue with account password</button> : null}
          {(flow === "forgot-passcode" || flow === "authentication-error") && offline ? <p className="mt-2 text-amber-200">Account password verification needs a network connection. Offline account authentication is not configured.</p> : null}
          {flow === "authentication-error" ? <button type="button" onClick={() => onFlow("security-lockout")} className="exa-focusable mt-3 text-emerald-300">View lockout requirements</button> : null}
          {flow === "security-lockout" && !offline ? <button type="button" onClick={onPassword} className="exa-focusable mt-3 min-h-10 w-full rounded-lg bg-emerald-700 px-3 font-semibold text-white hover:bg-emerald-600">Use account password</button> : null}
          {flow === "passkey-enrollment" ? <p className="mt-2">Required: server challenge, RP/origin binding, assertion verification, revocation and account recovery.</p> : null}
          {flow === "biometric-enrollment" ? <p className="mt-2">Browser biometrics may be offered through WebAuthn only after account-bound enrollment. No device secret is collected.</p> : null}
        </div>
      )}

      <button type="button" onClick={onBack} className="exa-focusable mt-5 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-white/[.09] text-sm text-slate-300 hover:bg-white/[.04]">
        <ArrowLeft size={15} /> Back to Smart Access
      </button>
    </section>
  );
}

export default function SmartAccessLogin({ onPassword, onCreateAccount, onForgotPassword }) {
  const { apiBaseUrl = getApiBaseUrl() } = useAuth();
  const connection = useConnectionState(apiBaseUrl || "");
  const presentation = getConnectionPresentation(connection);
  const offline = connection === "offline";
  const [passcode, setPasscode] = useState("");
  const [flow, setFlow] = useState(null);
  const [message, setMessage] = useState("");
  const methods = visibleSmartAccessMethods(offline);

  const appendDigit = (digit) => {
    setMessage("");
    if (passcode.length >= SMART_ACCESS_PASSCODE_LENGTH - 1) {
      setPasscode("");
      setFlow("authentication-error");
      return;
    }
    setPasscode((current) => `${current}${digit}`);
  };

  const chooseMethod = (method) => {
    setMessage("");
    if (method === "password") {
      onPassword();
      return;
    }
    setFlow(method === "passkey" ? "passkey-enrollment" : method === "biometric" ? "biometric-enrollment" : "device-credential");
  };

  return (
    <main className="min-h-screen overflow-y-auto bg-[#0D1322] px-4 pb-[max(20px,env(safe-area-inset-bottom))] pt-[max(18px,env(safe-area-inset-top))] text-slate-100">
      <div className="mx-auto flex min-h-[calc(100vh-38px)] w-full max-w-[420px] flex-col justify-center py-4">
        <header className="mb-5 flex min-h-10 items-center justify-between">
          <div className="flex items-center gap-2" aria-label="ExaEarn">
            <span className="grid h-8 w-8 place-items-center rounded-[10px] bg-emerald-300/10"><img src={Image.exaearnLogo} alt="" className="h-5 w-5 object-contain" /></span>
            <span className="text-sm font-semibold tracking-wide text-slate-100">exaearn</span>
          </div>
          <div className="flex items-center gap-2">
            <LanguageSwitcher compact />
            <span className={`inline-flex min-h-7 items-center gap-1.5 rounded-full border bg-white/[.02] px-2.5 text-[10px] font-medium ${presentation.color} ${presentation.border}`} role="status" aria-label={`Connection status: ${presentation.label}`}>
              {connection === "reconnecting" ? <RotateCw size={13} className="animate-spin motion-reduce:animate-none" /> : <presentation.Icon size={13} />}
              {presentation.label}
            </span>
          </div>
        </header>

        {flow ? (
          <SmartAccessFlow key={flow} flow={flow} offline={offline} onBack={() => setFlow(null)} onPassword={onPassword} onFlow={setFlow} />
        ) : (
          <>
            <div className="mx-auto mb-3 grid h-[62px] w-[62px] place-items-center rounded-full border border-emerald-300/25 bg-emerald-300/[.08] text-emerald-300">
              <ShieldCheck size={29} strokeWidth={1.7} aria-hidden="true" />
            </div>
            <h1 className="text-center text-[26px] font-semibold tracking-tight text-white">Welcome back</h1>
            <p className="mt-1 text-center text-sm text-slate-400">Enter your ExaEarn Passcode</p>

            <div className="mt-5"><PasscodeDots count={passcode.length} /></div>
            <div className="mx-auto mt-4 grid w-full max-w-[264px] grid-cols-3 gap-1" role="group" aria-label="Numeric passcode keypad">
              {["1", "2", "3", "4", "5", "6", "7", "8", "9", "biometric", "0", "back"].map((key, index) => (
                <button
                  key={`${key}-${index}`}
                  type="button"
                  onClick={() => key === "biometric" ? chooseMethod("biometric") : key === "back" ? setPasscode((current) => current.slice(0, -1)) : appendDigit(key)}
                  aria-label={key === "biometric" ? "Use biometric access" : key === "back" ? "Delete last passcode digit" : `Digit ${key}`}
                  className="exa-focusable grid h-11 place-items-center rounded-xl text-[21px] font-medium text-slate-100 transition hover:bg-white/[.05] motion-reduce:transition-none"
                >
                  {key === "biometric" ? <Fingerprint size={21} className="text-emerald-300" /> : key === "back" ? <Delete size={19} className="text-slate-300" /> : key}
                </button>
              ))}
            </div>

            {message ? <p role="alert" className="mt-2 text-center text-xs leading-5 text-amber-200">{message}</p> : null}
            <button type="button" onClick={() => { setPasscode(""); setFlow("forgot-passcode"); }} className="exa-focusable mx-auto mt-1 min-h-10 px-3 text-xs font-semibold text-emerald-300 hover:text-emerald-200">Forgot Passcode?</button>

            <div className="my-3 flex items-center gap-2.5 text-[9px] font-semibold tracking-[0.16em] text-slate-500">
              <span className="h-px flex-1 bg-white/[.09]" /> OTHER WAYS TO UNLOCK <span className="h-px flex-1 bg-white/[.09]" />
            </div>
            <div className="grid grid-cols-1 gap-2 min-[360px]:grid-cols-2">
              {methods.map((method) => {
                const { title, detail, Icon } = methodCopy[method];
                return (
                  <button key={method} type="button" onClick={() => chooseMethod(method)} className="exa-focusable flex min-h-[58px] items-center gap-2 rounded-xl border border-white/[.08] bg-[#191f2d] px-2.5 py-2 text-left transition hover:border-emerald-300/20 hover:bg-[#1d2533] motion-reduce:transition-none" aria-label={`${title}. ${detail}`}>
                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-emerald-300/[.08] text-emerald-300"><Icon size={17} /></span>
                    <span className="min-w-0 flex-1"><span className="block text-[11px] font-semibold text-slate-100">{title}</span><span className="mt-0.5 block text-[9px] leading-3 text-slate-500">{method === "biometric" ? "Not linked to account sign-in" : detail}</span></span>
                    <ChevronRight size={14} className="shrink-0 text-slate-600" />
                  </button>
                );
              })}
            </div>

            <div className={`mt-3 flex min-h-[68px] items-center gap-3 rounded-xl border p-3 ${offline ? "border-amber-300/20 bg-[#15372f]/55" : "border-emerald-300/15 bg-emerald-300/[.035]"}`}>
              <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-[11px] ${offline ? "bg-amber-300/10 text-amber-300" : "bg-emerald-300/10 text-emerald-300"}`}>{offline ? <Bluetooth size={17} /> : <LockKeyhole size={17} />}</span>
              <span><span className="block text-[11px] font-semibold text-slate-100">{offline ? "ExaPay" : "Seamless Access"}</span><span className="mt-0.5 block text-[9px] leading-4 text-slate-400">{offline ? "Offline ExaPay is SIMUSDT simulation only. Previously enrolled access and later reconciliation are required; this browser has no offline account authentication." : "Your entire financial ecosystem, securely within reach."}</span></span>
            </div>

            <button type="button" onClick={() => setFlow("create-passcode")} className="exa-focusable mx-auto mt-1 min-h-9 text-[10px] text-slate-500 hover:text-emerald-300">Set up or manage Smart Access</button>
            <p className="mt-1 text-center text-[10px] text-slate-500">New to ExaEarn? <button type="button" onClick={onCreateAccount} className="exa-focusable font-semibold text-emerald-300 hover:text-emerald-200">Create an account</button></p>
          </>
        )}

        <footer className="mt-4 text-center text-[9px] font-medium tracking-wide text-slate-600">Secured by ExaEarn Smart Access</footer>
      </div>
    </main>
  );
}
