import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Lock, Mail, Eye, EyeOff } from "lucide-react";
import { adminHttp } from "../services/http";
import { isDemoAdminEnabled } from "../config/apiConfig";
import { useAdminAuth } from "../context/AdminAuthContext";

const previewAdmin = {
  id: "preview-admin",
  name: "ExaEarn Preview Admin",
  email: "admin@exaearn.com",
  role: "super_admin",
  demo: true,
};

export function AdminLoginPage() {
  const navigate = useNavigate();
  const { login } = useAdminAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const demoAdminEnabled = isDemoAdminEnabled();

  const startPreviewSession = () => {
    login(`demo-admin-${Date.now()}`, previewAdmin);
    navigate("/dashboard");
  };

  const handleLogin = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError("");

    try {
      if (demoAdminEnabled) {
        startPreviewSession();
        return;
      }

      const response = await adminHttp.post("/login", {
        email,
        password,
        device_name: "admin-web",
      });

      if (response.data?.token) {
        login(response.data.token, response.data.admin);
        navigate("/dashboard");
      }
    } catch (err) {
      if (demoAdminEnabled) {
        startPreviewSession();
        return;
      }

      setError(err.response?.data?.message || "Login failed. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-cosmic-950" style={{
      backgroundImage: "radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.1) 0%, transparent 50%), radial-gradient(circle at 80% 80%, rgba(234, 185, 95, 0.05) 0%, transparent 50%)",
    }}>
      <div className="w-full max-w-md">
        <div className="bg-gradient-to-br from-cosmic-900/40 to-cosmic-950/60 border border-white/10 rounded-2xl backdrop-blur-xl p-8 shadow-2xl">
          <div className="text-center mb-8">
            <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-auric-300 to-violet-400 mb-4">
              <Lock className="w-7 h-7 text-cosmic-950" />
            </div>
            <h1 className="text-2xl font-semibold text-white mb-2">Admin Portal</h1>
            <p className="text-violet-100/60 text-sm">ExaEarn Command Center</p>
          </div>

          {error && (
            <div className="mb-4 p-3 rounded-lg bg-red-500/15 border border-red-500/30 text-red-300 text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleLogin} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-violet-100/80 mb-2">
                Email Address
              </label>
              <div className="relative">
                <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-violet-100/40" />
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="admin@exaearn.com"
                  className="w-full pl-10 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-violet-100/30 focus:outline-none focus:ring-2 focus:ring-violet-400/50 focus:border-transparent transition"
                  required
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-violet-100/80 mb-2">
                Password
              </label>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-violet-100/40" />
                <input
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className="w-full pl-10 pr-10 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-violet-100/30 focus:outline-none focus:ring-2 focus:ring-violet-400/50 focus:border-transparent transition"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-violet-100/40 hover:text-violet-100/60 transition"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full mt-6 py-3 px-4 bg-gradient-to-r from-auric-300 to-violet-400 text-cosmic-950 font-semibold rounded-xl hover:shadow-lg hover:shadow-violet-400/30 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {loading ? "Signing in..." : "Sign In"}
            </button>
          </form>

          <p className="text-center text-xs text-violet-100/40 mt-6">
            {demoAdminEnabled
              ? "Preview mode is enabled for dashboard testing. Real admin auth requires the deployed API."
              : "Authorized personnel only. All access is logged."}
          </p>
        </div>
      </div>
    </div>
  );
}
