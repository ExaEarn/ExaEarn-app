import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ArrowLeft, Camera, Check, Eye, ImagePlus, Loader2, Shield, Trash2, Upload, UserRound, X } from 'lucide-react';
import { useAuth } from '../../context/AuthContext.jsx';
import ProfileIdentity from '../../components/profile/ProfileIdentity.jsx';
import {
  fetchProfileAvatars,
  fetchProfileIdentity,
  removeProfileImage,
  selectProfileAvatar,
  updateProfileVisibility,
  uploadProfileImage,
  useProfileInitials as saveProfileInitials,
} from '../../services/profileIdentityApi.js';

const VISIBILITY_OPTIONS = [
  { key: 'self', label: 'Only me', description: 'Custom photos stay private and public areas use your avatar or initials.' },
  { key: 'p2p', label: 'P2P counterparties', description: 'Visible in protected P2P trade contexts where identity helps settlement trust.' },
  { key: 'public', label: 'Public', description: 'Visible across supported ExaEarn community and profile surfaces.' },
];

const DEFAULT_AVATAR_GROUPS = [
  {
    category: 'Classic',
    avatars: [
      { id: 'classic-gold', category: 'Classic', name: 'Gold Initial', mark: 'EX', accent: '#0b0712', background: 'linear-gradient(135deg, #f7d774, #b8860b)' },
      { id: 'classic-onyx', category: 'Classic', name: 'Onyx Glow', mark: 'EA', accent: '#f7d774', background: 'linear-gradient(135deg, #111827, #312e81)' },
    ],
  },
  {
    category: 'Web3',
    avatars: [
      { id: 'web3-orbit', category: 'Web3', name: 'Orbit', mark: 'ORB', accent: '#fef3c7', background: 'linear-gradient(135deg, #1d4ed8, #7c3aed)' },
      { id: 'web3-ledger', category: 'Web3', name: 'Ledger', mark: 'LED', accent: '#07111f', background: 'linear-gradient(135deg, #22d3ee, #facc15)' },
    ],
  },
  {
    category: 'Minimal',
    avatars: [
      { id: 'minimal-slate', category: 'Minimal', name: 'Slate', mark: 'E', accent: '#f8fafc', background: 'linear-gradient(135deg, #334155, #020617)' },
      { id: 'minimal-auric', category: 'Minimal', name: 'Auric Dot', mark: 'DOT', accent: '#111827', background: 'linear-gradient(135deg, #fde68a, #f59e0b)' },
    ],
  },
  {
    category: 'Futuristic',
    avatars: [
      { id: 'future-neon', category: 'Futuristic', name: 'Neon Field', mark: 'TRI', accent: '#e0f2fe', background: 'linear-gradient(135deg, #0891b2, #4c1d95)' },
      { id: 'future-quantum', category: 'Futuristic', name: 'Quantum Core', mark: 'CORE', accent: '#fefce8', background: 'linear-gradient(135deg, #be123c, #7c2d12)' },
    ],
  },
  {
    category: 'ExaEarn',
    avatars: [
      { id: 'exaearn-crown', category: 'ExaEarn', name: 'ExaEarn Crown', mark: 'EXAEARN', accent: '#0b0712', background: 'linear-gradient(135deg, #fbbf24, #fafafa)' },
      { id: 'exaearn-prime', category: 'ExaEarn', name: 'ExaEarn Prime', mark: 'PRIME', accent: '#facc15', background: 'linear-gradient(135deg, #050505, #3b0764)' },
    ],
  },
];

const flattenAvatars = (groups) => groups.flatMap((group) => group.avatars || []);

function ProfileAppearance({ onBack }) {
  const { user, setUser, request, apiBaseUrl } = useAuth();
  const [identity, setIdentity] = useState(user?.profile_identity || null);
  const [avatarGroups, setAvatarGroups] = useState([]);
  const [selectedAvatar, setSelectedAvatar] = useState(user?.avatar_id || user?.profile_identity?.avatar?.id || '');
  const [displayType, setDisplayType] = useState(user?.profile_display_type || user?.profile_identity?.display_type || 'initials');
  const [visibility, setVisibility] = useState(user?.profile_visibility || user?.profile_identity?.visibility || 'self');
  const [file, setFile] = useState(null);
  const [previewUrl, setPreviewUrl] = useState('');
  const [zoom, setZoom] = useState(1);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const inputRef = useRef(null);
  const visibleAvatarGroups = avatarGroups.length ? avatarGroups : DEFAULT_AVATAR_GROUPS;
  const firstAvatarId = useMemo(() => flattenAvatars(visibleAvatarGroups)[0]?.id || '', [visibleAvatarGroups]);

  const activateAvatarMode = () => {
    setDisplayType('avatar');
    setSelectedAvatar((current) => current || firstAvatarId);
  };

  useEffect(() => {
    let mounted = true;
    async function load() {
      setLoading(true);
      setError('');
      try {
        const [catalog, current] = await Promise.all([fetchProfileAvatars(request), fetchProfileIdentity(request)]);
        if (!mounted) return;
        const safeCatalog = Array.isArray(catalog) && catalog.length ? catalog : DEFAULT_AVATAR_GROUPS;
        const firstCatalogAvatarId = flattenAvatars(safeCatalog)[0]?.id || '';
        setAvatarGroups(safeCatalog);
        setIdentity(current.identity || current.user?.profile_identity || null);
        const nextUser = current.user || user;
        if (nextUser) setUser(nextUser);
        setSelectedAvatar(nextUser?.avatar_id || current.identity?.avatar?.id || firstCatalogAvatarId);
        setDisplayType(nextUser?.profile_display_type || current.identity?.display_type || 'initials');
        setVisibility(nextUser?.profile_visibility || current.identity?.visibility || 'self');
      } catch (loadError) {
        if (mounted) setError(loadError.message || 'Unable to load profile appearance.');
      } finally {
        if (mounted) setLoading(false);
      }
    }
    load();
    return () => { mounted = false; };
  }, [request, setUser]);

  useEffect(() => {
    if (!file) {
      setPreviewUrl('');
      return undefined;
    }
    const nextUrl = URL.createObjectURL(file);
    setPreviewUrl(nextUrl);
    return () => URL.revokeObjectURL(nextUrl);
  }, [file]);

  const previewIdentity = useMemo(() => {
    if (displayType === 'custom_image' && previewUrl) {
      return { display_type: 'custom_image', thumbnail_url: previewUrl, image_url: previewUrl, initials: identity?.initials, gradient: identity?.gradient };
    }
    if (displayType === 'avatar') {
      const avatar = flattenAvatars(visibleAvatarGroups).find((item) => item.id === selectedAvatar) || identity?.avatar;
      return { ...identity, display_type: avatar ? 'avatar' : 'initials', avatar };
    }
    return { ...identity, display_type: 'initials', avatar: null, image_url: '', thumbnail_url: '' };
  }, [displayType, identity, previewUrl, selectedAvatar, visibleAvatarGroups]);

  const applyUser = (updatedUser) => {
    if (!updatedUser?.id) return;
    setUser(updatedUser);
    setIdentity(updatedUser.profile_identity || null);
    setSelectedAvatar(updatedUser.avatar_id || updatedUser.profile_identity?.avatar?.id || '');
    setDisplayType(updatedUser.profile_display_type || updatedUser.profile_identity?.display_type || 'initials');
    setVisibility(updatedUser.profile_visibility || updatedUser.profile_identity?.visibility || visibility);
  };

  const save = async () => {
    setSaving(true);
    setMessage('');
    setError('');
    try {
      let updatedUser;
      if (displayType === 'custom_image') {
        if (!file && !identity?.image_url && !identity?.thumbnail_url) {
          throw new Error('Choose an image before saving custom image mode.');
        }
        if (file) {
          updatedUser = await uploadProfileImage(request, { file, visibility, crop: { zoom } });
        } else {
          updatedUser = await updateProfileVisibility(request, { visibility });
        }
      } else if (displayType === 'avatar') {
        const avatarId = selectedAvatar || firstAvatarId;
        if (!avatarId) throw new Error('Select an ExaEarn avatar.');
        updatedUser = await selectProfileAvatar(request, { avatarId, visibility });
      } else {
        updatedUser = await saveProfileInitials(request, { visibility });
      }
      applyUser(updatedUser);
      setFile(null);
      setMessage('Profile appearance saved successfully.');
    } catch (saveError) {
      setError(saveError.message || 'Unable to save profile appearance.');
    } finally {
      setSaving(false);
    }
  };

  const removeImage = async () => {
    setSaving(true);
    setError('');
    setMessage('');
    try {
      const updatedUser = await removeProfileImage(request);
      applyUser(updatedUser);
      setFile(null);
      setDisplayType(updatedUser.avatar_id ? 'avatar' : 'initials');
      setMessage('Profile image removed.');
    } catch (removeError) {
      setError(removeError.message || 'Unable to remove profile image.');
    } finally {
      setSaving(false);
    }
  };

  const selectFile = (event) => {
    const nextFile = event.target.files?.[0];
    setError('');
    if (!nextFile) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(nextFile.type)) {
      setError('Upload a JPG, PNG or WebP image.');
      return;
    }
    if (nextFile.size > 5 * 1024 * 1024) {
      setError('Profile image must be 5 MB or smaller.');
      return;
    }
    setFile(nextFile);
    setDisplayType('custom_image');
  };

  return (
    <div className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <div className="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-3 pb-6 pt-4 sm:px-5 sm:pt-6">
        <header className="mb-4 flex items-center gap-3">
          <button type="button" onClick={onBack} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-2 text-[var(--exa-text-primary)] hover:border-[var(--exa-border-active)]" aria-label="Back to profile">
            <ArrowLeft className="h-5 w-5" />
          </button>
          <div className="min-w-0">
            <p className="text-xs uppercase tracking-[0.2em] text-[var(--exa-gold-light)]">Profile Settings</p>
            <h1 className="font-['Sora'] text-xl font-semibold sm:text-2xl">Profile Appearance</h1>
          </div>
        </header>

        <main className="grid flex-1 gap-4 lg:grid-cols-[320px_1fr]">
          <aside className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl">
            <p className="text-sm font-semibold text-[var(--exa-text-primary)]">Preview</p>
            <div className="mt-5 flex flex-col items-center rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5 text-center">
              <ProfileIdentity user={user} identity={previewIdentity} apiBaseUrl={apiBaseUrl} size="xl" />
              <p className="mt-3 text-lg font-semibold text-[var(--exa-text-primary)]">{user?.name || 'ExaEarn User'}</p>
              <p className="text-xs text-[var(--exa-text-muted)]">UID {user?.unique_user_id || 'Pending'}</p>
              <div className="mt-3 inline-flex items-center gap-1 rounded-full border border-[var(--exa-border)] px-2 py-1 text-xs text-[var(--exa-text-secondary)]">
                <Shield className="h-3.5 w-3.5 text-[var(--exa-gold-light)]" /> KYC level {user?.verification?.kyc_level ?? user?.kyc_level ?? 0}
              </div>
            </div>
            <p className="mt-4 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-3 text-xs leading-5 text-[var(--exa-gold-light)]">
              Your profile picture is separate from identity verification. Verified badges come only from ExaEarn KYC status.
            </p>
          </aside>

          <section className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl sm:p-5">
            {loading ? (
              <div className="flex min-h-[360px] items-center justify-center text-[var(--exa-text-secondary)]"><Loader2 className="mr-2 h-4 w-4 animate-spin" /> Loading appearance...</div>
            ) : (
              <div className="space-y-5">
                <section>
                  <h2 className="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--exa-text-secondary)]">Display Method</h2>
                  <div className="mt-3 grid gap-2 sm:grid-cols-3">
                    <ChoiceButton active={displayType === 'initials'} icon={UserRound} label="Use initials" onClick={() => setDisplayType('initials')} />
                    <ChoiceButton active={displayType === 'avatar'} icon={ImagePlus} label="ExaEarn avatar" onClick={activateAvatarMode} />
                    <ChoiceButton active={displayType === 'custom_image'} icon={Camera} label="Custom image" onClick={() => setDisplayType('custom_image')} />
                  </div>
                </section>

                {displayType === 'custom_image' ? (
                  <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <h3 className="font-semibold text-[var(--exa-text-primary)]">Upload profile picture</h3>
                        <p className="text-xs text-[var(--exa-text-muted)]">JPG, PNG or WebP. Max 5 MB. Images are re-encoded to WebP server-side.</p>
                      </div>
                      <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={selectFile} />
                      <button type="button" onClick={() => inputRef.current?.click()} className="inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border-active)] px-3 py-2 text-sm font-semibold text-[var(--exa-gold-light)]">
                        <Upload className="h-4 w-4" /> Choose image
                      </button>
                    </div>
                    {previewUrl ? (
                      <div className="mt-4 grid gap-4 sm:grid-cols-[180px_1fr]">
                        <div className="aspect-square overflow-hidden rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)]">
                          <img src={previewUrl} alt="Profile upload preview" className="h-full w-full object-cover" style={{ transform: `scale(${zoom})` }} />
                        </div>
                        <label className="text-sm text-[var(--exa-text-secondary)]">
                          Crop zoom
                          <input type="range" min="1" max="2" step="0.05" value={zoom} onChange={(event) => setZoom(Number(event.target.value))} className="mt-3 w-full accent-amber-400" />
                        </label>
                      </div>
                    ) : null}
                    {(identity?.image_url || identity?.thumbnail_url) ? (
                      <button type="button" onClick={removeImage} disabled={saving} className="mt-4 inline-flex items-center gap-2 rounded-xl border border-red-400/35 px-3 py-2 text-sm text-red-200 disabled:opacity-60">
                        <Trash2 className="h-4 w-4" /> Remove current image
                      </button>
                    ) : null}
                  </section>
                ) : null}

                {displayType === 'avatar' ? (
                  <section className="space-y-4">
                    {visibleAvatarGroups.map((group) => (
                      <div key={group.category}>
                        <h3 className="mb-2 text-sm font-semibold text-[var(--exa-text-secondary)]">{group.category}</h3>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                          {(group.avatars || []).map((avatar) => (
                            <button key={avatar.id} type="button" onClick={() => setSelectedAvatar(avatar.id)} className={`rounded-2xl border p-3 text-left transition ${selectedAvatar === avatar.id ? 'border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]' : 'border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] hover:border-[var(--exa-border-active)]'}`}>
                              <span className="flex items-center gap-3">
                                <span className="flex h-10 w-10 items-center justify-center rounded-full text-xs font-bold" style={{ background: avatar.background, color: avatar.accent }}>{avatar.mark}</span>
                                <span className="min-w-0">
                                  <span className="block truncate text-sm font-semibold text-[var(--exa-text-primary)]">{avatar.name}</span>
                                  <span className="text-xs text-[var(--exa-text-muted)]">{avatar.category}</span>
                                </span>
                              </span>
                            </button>
                          ))}
                        </div>
                      </div>
                    ))}
                  </section>
                ) : null}

                <section>
                  <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.16em] text-[var(--exa-text-secondary)]"><Eye className="h-4 w-4" /> Privacy</h2>
                  <div className="mt-3 grid gap-2">
                    {VISIBILITY_OPTIONS.map((option) => (
                      <button key={option.key} type="button" onClick={() => setVisibility(option.key)} className={`rounded-2xl border p-3 text-left ${visibility === option.key ? 'border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]' : 'border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]'}`}>
                        <span className="flex items-start gap-3">
                          <span className={`mt-0.5 flex h-5 w-5 items-center justify-center rounded-full border ${visibility === option.key ? 'border-[var(--exa-border-active)] bg-[var(--exa-gold)] text-[var(--exa-gold-contrast)]' : 'border-[var(--exa-border)]'}`}>{visibility === option.key ? <Check className="h-3 w-3" /> : null}</span>
                          <span><span className="block text-sm font-semibold text-[var(--exa-text-primary)]">{option.label}</span><span className="mt-1 block text-xs leading-5 text-[var(--exa-text-muted)]">{option.description}</span></span>
                        </span>
                      </button>
                    ))}
                  </div>
                </section>

                {message ? <div className="rounded-2xl border border-emerald-400/30 bg-emerald-400/10 p-3 text-sm text-emerald-100">{message}</div> : null}
                {error ? <div className="rounded-2xl border border-red-400/30 bg-red-400/10 p-3 text-sm text-red-100">{error}</div> : null}

                <div className="sticky bottom-3 z-10 flex gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-2 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl">
                  <button type="button" onClick={onBack} className="flex-1 rounded-xl border border-[var(--exa-border)] px-4 py-3 text-sm font-semibold text-[var(--exa-text-primary)] hover:border-[var(--exa-border-active)]"><X className="mr-2 inline h-4 w-4" /> Cancel</button>
                  <button type="button" onClick={save} disabled={saving} className="flex-1 rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-3 text-sm font-bold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:opacity-60">
                    {saving ? <><Loader2 className="mr-2 inline h-4 w-4 animate-spin" /> Saving...</> : 'Save Appearance'}
                  </button>
                </div>
              </div>
            )}
          </section>
        </main>
      </div>
    </div>
  );
}

function ChoiceButton({ active, icon: Icon, label, onClick }) {
  return (
    <button type="button" onClick={onClick} className={`flex items-center gap-2 rounded-2xl border px-3 py-3 text-sm font-semibold ${active ? 'border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]' : 'border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)]'}`}>
      <Icon className="h-4 w-4" /> {label}
    </button>
  );
}

export default ProfileAppearance;