import { useEffect, useState } from "react";
import {
  ArrowLeft,
  Eye,
  EyeOff,
  Loader2,
  Smartphone,
  ShieldAlert,
  ShieldCheck,
  CheckCircle2,
  X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const COUNTRY_CODES = [
  { code: "NG", name: "Nigeria", prefix: "+234" },
  { code: "US", name: "United States", prefix: "+1" },
  { code: "GB", name: "United Kingdom", prefix: "+44" },
  { code: "KE", name: "Kenya", prefix: "+254" },
  { code: "ZA", name: "South Africa", prefix: "+27" },
  { code: "GH", name: "Ghana", prefix: "+233" },
  { code: "IN", name: "India", prefix: "+91" },
  { code: "CA", name: "Canada", prefix: "+1" },
  { code: "AU", name: "Australia", prefix: "+61" },
  { code: "DE", name: "Germany", prefix: "+49" },
];

function PhoneVerification({ onBack }) {
  const { request, isLoading: authLoading } = useAuth();
  const [state, setState] = useState("loading"); // loading, not_added, unverified, verified, adding, verifying
  const [phoneData, setPhoneData] = useState({
    phone: null,
    phone_country_code: null,
    phone_normalized: null,
    phone_verified: false,
    last_verified_at: null,
  });

  // Adding phone form
  const [countryCode, setCountryCode] = useState("NG");
  const [phoneNumber, setPhoneNumber] = useState("");
  const [showPhoneError, setShowPhoneError] = useState("");
  const [addingPhone, setAddingPhone] = useState(false);

  // OTP verification
  const [otp, setOtp] = useState("");
  const [otpError, setOtpError] = useState("");
  const [verifyingOtp, setVerifyingOtp] = useState(false);
  const [otpExpiresAt, setOtpExpiresAt] = useState(null);
  const [otpTimeRemaining, setOtpTimeRemaining] = useState(0);

  // General error/success
  const [error, setError] = useState("");
  const [successMessage, setSuccessMessage] = useState("");
  const [showChangeConfirm, setShowChangeConfirm] = useState(false);

  // Load initial state
  useEffect(() => {
    let ignore = false;

    const loadPhoneStatus = async () => {
      try {
        const response = await request("/api/security/phone");
        if (!ignore) {
          if (response.success) {
            const { state: currentState, ...phoneInfo } = response.data;
            setPhoneData(phoneInfo);
            setState(currentState === "not_added" ? "not_added" : currentState === "pending" ? "unverified" : "verified");
          }
        }
      } catch (err) {
        if (!ignore) {
          setError(err.message || "Failed to load phone verification status.");
          setState("not_added");
        }
      }
    };

    loadPhoneStatus();
    return () => {
      ignore = true;
    };
  }, [request]);

  // OTP timer
  useEffect(() => {
    if (!otpExpiresAt) return;

    const timer = setInterval(() => {
      const now = Date.now();
      const remaining = Math.max(0, Math.floor((otpExpiresAt - now) / 1000));
      setOtpTimeRemaining(remaining);

      if (remaining <= 0) {
        clearInterval(timer);
      }
    }, 1000);

    return () => clearInterval(timer);
  }, [otpExpiresAt]);

  const handleRequestOtp = async (e) => {
    e.preventDefault();
    setShowPhoneError("");
    setError("");

    // Validate phone number
    if (!phoneNumber.trim()) {
      setShowPhoneError("Phone number is required.");
      return;
    }

    setAddingPhone(true);

    try {
      const response = await request("/api/security/phone/request", {
        method: "POST",
        body: JSON.stringify({
          country_code: countryCode,
          phone: phoneNumber.trim(),
        }),
      });

      if (response.success) {
        setOtpExpiresAt(new Date(response.data.expires_at).getTime());
        setState("verifying");
        setOtp("");
      } else if (response.code === "SMS_PROVIDER_REQUIRED") {
        setError("SMS provider not configured. Phone verification is ready but requires production SMS provider setup.");
      } else {
        setError(response.message || "Failed to send OTP.");
      }
    } catch (err) {
      setError(err.message || "Failed to request OTP.");
    } finally {
      setAddingPhone(false);
    }
  };

  const handleVerifyOtp = async (e) => {
    e.preventDefault();
    setOtpError("");
    setError("");

    if (!otp || otp.length !== 6) {
      setOtpError("Please enter a valid 6-digit code.");
      return;
    }

    setVerifyingOtp(true);

    try {
      const response = await request("/api/security/phone/verify", {
        method: "POST",
        body: JSON.stringify({
          otp,
          phone: phoneNumber.trim(),
        }),
      });

      if (response.success) {
        setSuccessMessage("Phone number verified successfully!");
        setPhoneData({
          phone: response.data.phone,
          phone_country_code: countryCode,
          phone_normalized: response.data.phone,
          phone_verified: true,
          last_verified_at: new Date().toISOString(),
        });
        setState("verified");
        setPhoneNumber("");
        setOtp("");
        setOtpExpiresAt(null);
      } else {
        setOtpError(response.message || "Verification failed.");
      }
    } catch (err) {
      setOtpError(err.message || "Failed to verify OTP.");
    } finally {
      setVerifyingOtp(false);
    }
  };

  const handleChangePhone = () => {
    setShowChangeConfirm(false);
    setState("adding");
    setPhoneNumber("");
    setOtp("");
    setOtpExpiresAt(null);
    setShowPhoneError("");
    setOtpError("");
    setError("");
  };

  const maskPhone = (phone) => {
    if (!phone) return "";
    const str = phone.replace(/\D/g, "");
    if (str.length < 4) return phone;
    const last4 = str.slice(-4);
    const masked = "*".repeat(Math.max(0, str.length - 4));
    return `+${str.slice(0, 3)} ${masked} ${last4}`;
  };

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-white">
      <header
        className="sticky top-0 z-30 border-b border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-2xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[var(--exa-text-primary)] sm:text-xl">Phone Number Verification</h1>
              <p className="text-xs text-[var(--exa-text-muted)] sm:text-sm">Add or verify your phone for account recovery</p>
            </div>
          </div>
        </div>
      </header>

      <section className="mx-auto w-full max-w-2xl px-4 pb-8 pt-5 sm:px-6">
        {/* Info Messages */}
        {error ? (
          <div className="mb-4 rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-3 text-sm text-[#FECACA]">
            <p className="flex items-center gap-2">
              <ShieldAlert className="h-4 w-4 text-[#F87171]" />
              {error}
            </p>
          </div>
        ) : null}

        {successMessage ? (
          <div className="mb-4 rounded-2xl border border-[#22C55E]/35 bg-[#22C55E]/10 p-3 text-sm text-[#BBFDD5]">
            <p className="flex items-center gap-2">
              <CheckCircle2 className="h-4 w-4 text-[#22C55E]" />
              {successMessage}
            </p>
          </div>
        ) : null}

        {/* Not Added State */}
        {state === "not_added" ? (
          <div className="space-y-4">
            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]">
                  <Smartphone className="h-5 w-5" />
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-[var(--exa-text-primary)]">No phone number added</h3>
                  <p className="mt-1 text-sm text-[var(--exa-text-muted)]">
                    Add a verified phone number to strengthen account security and enable recovery options.
                  </p>
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-3 text-sm text-[var(--exa-gold-light)]">
              <p className="flex items-center gap-2">
                <ShieldCheck className="h-4 w-4" />
                Your phone number will be encrypted and securely stored.
              </p>
            </div>

            <form onSubmit={handleRequestOtp} className="space-y-3">
              {/* Country Code Select */}
              <label className="block">
                <span className="mb-1.5 block text-sm text-[var(--exa-text-secondary)]">Country</span>
                <select
                  value={countryCode}
                  onChange={(e) => setCountryCode(e.target.value)}
                  className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2.5 text-sm text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]"
                >
                  {COUNTRY_CODES.map((country) => (
                    <option key={country.code} value={country.code} className="bg-[var(--exa-bg-primary)]">
                      {country.name} ({country.prefix})
                    </option>
                  ))}
                </select>
              </label>

              {/* Phone Number Input */}
              <label className="block">
                <span className="mb-1.5 block text-sm text-[var(--exa-text-secondary)]">Phone Number</span>
                <input
                  type="tel"
                  value={phoneNumber}
                  onChange={(e) => {
                    setPhoneNumber(e.target.value);
                    setShowPhoneError("");
                  }}
                  placeholder="Enter phone number"
                  className={`w-full rounded-xl border bg-[var(--exa-surface-hover)] px-3 py-2.5 text-sm outline-none transition ${
                    showPhoneError
                      ? "border-[#EF4444]/65 focus:border-[#EF4444]"
                      : "border-[var(--exa-border)] focus:border-[var(--exa-border-active)]"
                  }`}
                />
                {showPhoneError ? <p className="mt-1 text-xs text-[#FCA5A5]">{showPhoneError}</p> : null}
              </label>

              <button
                type="submit"
                disabled={!phoneNumber.trim() || addingPhone}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl exa-button-primary py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:cursor-not-allowed disabled:opacity-45"
              >
                {addingPhone ? <Loader2 className="h-4 w-4 animate-spin" /> : <Smartphone className="h-4 w-4" />}
                {addingPhone ? "Sending..." : "Send Verification Code"}
              </button>
            </form>
          </div>
        ) : null}

        {/* Verifying State (OTP Entry) */}
        {state === "verifying" ? (
          <div className="space-y-4">
            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <h3 className="font-semibold text-[var(--exa-text-primary)]">Enter Verification Code</h3>
              <p className="mt-2 text-sm text-[var(--exa-text-muted)]">
                We sent a 6-digit code to <strong>{maskPhone(`+${COUNTRY_CODES.find((c) => c.code === countryCode)?.prefix.replace("+", "")}${phoneNumber}`)}</strong>
              </p>
            </div>

            <form onSubmit={handleVerifyOtp} className="space-y-3">
              <label className="block">
                <span className="mb-1.5 block text-sm text-[var(--exa-text-secondary)]">Verification Code</span>
                <input
                  type="text"
                  value={otp}
                  onChange={(e) => {
                    setOtp(e.target.value.replace(/\D/g, "").slice(0, 6));
                    setOtpError("");
                  }}
                  placeholder="000000"
                  maxLength="6"
                  className={`w-full rounded-xl border bg-[var(--exa-surface-hover)] px-3 py-2.5 text-center text-2xl tracking-widest outline-none transition font-mono ${
                    otpError
                      ? "border-[#EF4444]/65 focus:border-[#EF4444]"
                      : "border-[var(--exa-border)] focus:border-[var(--exa-border-active)]"
                  }`}
                />
                {otpError ? <p className="mt-1 text-xs text-[#FCA5A5]">{otpError}</p> : null}
              </label>

              <div className="text-xs text-[var(--exa-text-muted)]">
                {otpTimeRemaining > 0 ? (
                  <>Expires in {otpTimeRemaining} seconds</>
                ) : (
                  <button
                    type="button"
                    onClick={handleRequestOtp}
                    disabled={addingPhone}
                    className="text-[var(--exa-gold-light)] hover:underline disabled:opacity-50"
                  >
                    Resend Code
                  </button>
                )}
              </div>

              <button
                type="submit"
                disabled={otp.length !== 6 || verifyingOtp}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl exa-button-primary py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:cursor-not-allowed disabled:opacity-45"
              >
                {verifyingOtp ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
                {verifyingOtp ? "Verifying..." : "Verify Phone Number"}
              </button>

              <button
                type="button"
                onClick={() => {
                  setState("not_added");
                  setPhoneNumber("");
                  setOtp("");
                  setOtpExpiresAt(null);
                  setShowPhoneError("");
                  setOtpError("");
                }}
                className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] py-2.5 text-sm font-semibold text-[var(--exa-text-secondary)]"
              >
                Cancel
              </button>
            </form>
          </div>
        ) : null}

        {/* Verified State */}
        {state === "verified" ? (
          <div className="space-y-4">
            <div className="rounded-2xl border border-[#22C55E]/35 bg-[#22C55E]/10 p-4 sm:p-5">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#22C55E]/50 bg-[#22C55E]/10 text-[#22C55E]">
                  <CheckCircle2 className="h-5 w-5" />
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-[#86EFAC]">Phone Verified</h3>
                  <p className="mt-1 text-sm text-[#BBF7D0]">Your phone number is verified and secured.</p>
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-[var(--exa-text-muted)]">Phone Number</span>
                  <span className="font-mono font-semibold text-[var(--exa-text-primary)]">{maskPhone(phoneData.phone_normalized)}</span>
                </div>
                <div className="flex items-center justify-between border-t border-[var(--exa-border)] pt-3">
                  <span className="text-sm text-[var(--exa-text-muted)]">Status</span>
                  <span className="inline-flex items-center gap-1.5 rounded-full border border-[#22C55E]/35 bg-[#22C55E]/10 px-2.5 py-1 text-xs font-semibold text-[#22C55E]">
                    <span className="h-1.5 w-1.5 rounded-full bg-[#22C55E]" />
                    Verified
                  </span>
                </div>
                {phoneData.last_verified_at ? (
                  <div className="flex items-center justify-between border-t border-[var(--exa-border)] pt-3">
                    <span className="text-sm text-[var(--exa-text-muted)]">Verified On</span>
                    <span className="text-sm text-[var(--exa-text-primary)]">
                      {new Date(phoneData.last_verified_at).toLocaleDateString(undefined, {
                        year: "numeric",
                        month: "short",
                        day: "numeric",
                      })}
                    </span>
                  </div>
                ) : null}
              </div>
            </div>

            <div className="rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-3 text-sm text-[var(--exa-gold-light)]">
              <p className="flex items-start gap-2">
                <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" />
                <span>Your verified phone is now available for account recovery and security notifications.</span>
              </p>
            </div>

            <button
              type="button"
              onClick={() => setShowChangeConfirm(true)}
              className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] py-2.5 text-sm font-semibold text-[var(--exa-text-secondary)] hover:bg-[var(--exa-surface-hover)] hover:text-[var(--exa-text-primary)]"
            >
              Change Phone Number
            </button>
          </div>
        ) : null}

        {/* Change Confirmation Modal */}
        {showChangeConfirm ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div className="w-full max-w-sm rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-300/35 bg-amber-400/10 text-amber-300">
                  <ShieldAlert className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-semibold text-[var(--exa-text-primary)]">Change Phone Number?</h3>
                  <p className="mt-1 text-sm text-[var(--exa-text-muted)]">
                    Changing your phone number will require re-verification. Your current phone will no longer be verified.
                  </p>
                </div>
              </div>

              <div className="mt-5 flex gap-2">
                <button
                  type="button"
                  onClick={() => setShowChangeConfirm(false)}
                  className="flex-1 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] py-2 text-sm font-semibold text-[var(--exa-text-secondary)]"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleChangePhone}
                  className="flex-1 rounded-xl exa-button-primary py-2 text-sm font-semibold text-[var(--exa-gold-contrast)]"
                >
                  Change
                </button>
              </div>
            </div>
          </div>
        ) : null}
      </section>
    </main>
  );
}

export default PhoneVerification;
