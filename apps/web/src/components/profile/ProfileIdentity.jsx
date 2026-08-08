import React, { useMemo, useState } from 'react';

const SIZE_MAP = {
  xs: 'h-7 w-7 text-[10px]',
  sm: 'h-9 w-9 text-xs',
  md: 'h-12 w-12 text-sm',
  lg: 'h-16 w-16 text-lg',
  xl: 'h-24 w-24 text-2xl',
};

const FALLBACK_GRADIENTS = [
  'linear-gradient(135deg,#2b1805,#d4af37)',
  'linear-gradient(135deg,#111827,#7c3aed)',
  'linear-gradient(135deg,#0f172a,#0ea5e9)',
  'linear-gradient(135deg,#1f1307,#f59e0b)',
  'linear-gradient(135deg,#07110f,#10b981)',
  'linear-gradient(135deg,#160b1f,#e879f9)',
];

function initialsFor(user) {
  const name = user?.name?.trim() || user?.nickname?.trim() || user?.email?.split('@')[0] || 'ExaEarn User';
  const parts = name.split(/\s+/).filter(Boolean).slice(0, 2);
  return parts.map((part) => part[0]?.toUpperCase()).join('') || 'EX';
}

function gradientFor(seed) {
  const text = String(seed || 'exaearn');
  let hash = 0;
  for (let index = 0; index < text.length; index += 1) hash = (hash * 31 + text.charCodeAt(index)) >>> 0;
  return FALLBACK_GRADIENTS[hash % FALLBACK_GRADIENTS.length];
}

function resolveUrl(url, apiBaseUrl) {
  if (!url) return '';
  if (/^https?:\/\//i.test(url) || url.startsWith('data:')) return url;
  const base = apiBaseUrl?.replace(/\/+$/, '') || '';
  return `${base}${url.startsWith('/') ? url : `/${url}`}`;
}

export default function ProfileIdentity({ user, identity, apiBaseUrl, size = 'md', className = '', alt = 'Profile identity' }) {
  const [imageFailed, setImageFailed] = useState(false);
  const resolved = identity || user?.profile_identity || {};
  const initials = resolved.initials || initialsFor(user);
  const imageUrl = !imageFailed ? resolveUrl(resolved.thumbnail_url || resolved.image_url || user?.picture, apiBaseUrl) : '';
  const avatar = resolved.avatar;
  const background = avatar?.background || resolved.gradient || gradientFor(user?.id || user?.unique_user_id || user?.email);
  const sizeClass = SIZE_MAP[size] || SIZE_MAP.md;

  const style = useMemo(() => ({ background }), [background]);

  return (
    <span
      className={`profile-identity ${sizeClass} ${className}`}
      style={style}
      aria-label={alt}
      role="img"
    >
      {imageUrl ? (
        <img src={imageUrl} alt={alt} onError={() => setImageFailed(true)} />
      ) : avatar ? (
        <span className="profile-identity-mark" style={{ color: avatar.accent || '#fff' }}>{avatar.mark || initials}</span>
      ) : (
        <span className="profile-identity-mark">{initials}</span>
      )}
    </span>
  );
}

export { initialsFor, gradientFor };