export async function fetchProfileIdentity(request) {
  const payload = await request('/api/profile/identity', { method: 'GET' });
  return payload.data || payload;
}

export async function fetchProfileAvatars(request) {
  const payload = await request('/api/profile/avatars', { method: 'GET' });
  return payload.data || [];
}

export async function selectProfileAvatar(request, { avatarId, visibility }) {
  const payload = await request('/api/profile/avatar', {
    method: 'POST',
    body: JSON.stringify({ avatar_id: avatarId, visibility }),
  });
  return payload.data || payload.user || payload;
}

export async function useProfileInitials(request, { visibility }) {
  const payload = await request('/api/profile/initials', {
    method: 'POST',
    body: JSON.stringify({ visibility }),
  });
  return payload.data || payload.user || payload;
}

export async function updateProfileVisibility(request, { visibility }) {
  const payload = await request('/api/profile/visibility', {
    method: 'PATCH',
    body: JSON.stringify({ visibility }),
  });
  return payload.data || payload.user || payload;
}

export async function removeProfileImage(request) {
  const payload = await request('/api/profile/image', { method: 'DELETE' });
  return payload.data || payload.user || payload;
}

export async function uploadProfileImage(request, { file, visibility, crop }) {
  const formData = new FormData();
  formData.append('image', file);
  formData.append('visibility', visibility);
  if (crop) {
    Object.entries(crop).forEach(([key, value]) => formData.append(`crop[${key}]`, String(value)));
  }

  const payload = await request('/api/profile/image', {
    method: 'POST',
    body: formData,
    timeoutMs: 30000,
  });
  return payload.data || payload.user || payload;
}