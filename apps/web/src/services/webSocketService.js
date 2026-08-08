import { useEffect } from 'react';
import { io } from 'socket.io-client';
import { getNodeServiceUrl, isLocalApiPreview } from '../config/apiConfig';

let eventSource = null;
let socket = null;
const listeners = {};

const dispatchEvent = (eventName, payload) => {
  if (!listeners[eventName]) {
    return;
  }

  listeners[eventName].forEach((callback) => callback(payload));
};

const handleSseMessage = (event) => {
  try {
    const data = JSON.parse(event.data);
    dispatchEvent(event.type || 'message', data);
  } catch (error) {
    console.warn('Failed to parse SSE event payload', event, error);
  }
};

export const initializeWebSocket = (baseUrl) => {
  const nodeBaseUrl = getNodeServiceUrl();

  if (isLocalApiPreview()) {
    return;
  }

  if ((socket || eventSource) || (!nodeBaseUrl && !baseUrl)) {
    return;
  }

  if (nodeBaseUrl) {
    socket = io(nodeBaseUrl, {
      path: '/ws/wallet',
      transports: ['websocket'],
      withCredentials: true,
    });

    socket.on('connect', () => {
      try {
        const raw = localStorage.getItem('exaearn_auth_user');
        const parsed = raw ? JSON.parse(raw) : null;
        const userId = Number(parsed?.id || parsed?.user?.id);

        if (Number.isFinite(userId) && userId > 0) {
          socket.emit('subscribe:user', { user_id: userId });
        }
      } catch (error) {
        console.warn('Failed to subscribe websocket user room', error);
      }
    });

    socket.on('connect_error', (error) => {
      console.warn('Socket connection error:', error?.message || error);
    });

    socket.on('exapoint:update', (data) => {
      dispatchEvent('exapoint:update', data);
    });

    socket.on('portfolio:update', (data) => {
      dispatchEvent('portfolio:update', data);
    });

    socket.on('price:update', (data) => {
      dispatchEvent('price:update', data);
    });

    socket.on('game.flight', (data) => {
      dispatchEvent('game.flight', data);
    });

    return;
  }

  const endpoint = `${baseUrl.replace(/\/+$/, '')}/api/events/subscribe`;
  eventSource = new EventSource(endpoint, { withCredentials: true });

  eventSource.addEventListener('user.created', handleSseMessage);
  eventSource.addEventListener('portfolio:update', handleSseMessage);
  eventSource.addEventListener('price:update', handleSseMessage);
  eventSource.addEventListener('market:stream', handleSseMessage);
  eventSource.addEventListener('game.flight', handleSseMessage);
  eventSource.addEventListener('open', () => {
    console.info('SSE connection opened:', endpoint);
  });
  eventSource.addEventListener('error', (error) => {
    console.warn('SSE connection error:', error);
  });
};

export const closeWebSocket = () => {
  if (eventSource) {
    eventSource.close();
    eventSource = null;
  }

  if (socket) {
    socket.disconnect();
    socket = null;
  }
};

export const onEvent = (eventName, callback) => {
  if (!listeners[eventName]) {
    listeners[eventName] = [];
  }
  listeners[eventName].push(callback);

  return () => {
    listeners[eventName] = listeners[eventName].filter((cb) => cb !== callback);
  };
};

export const useWebSocketEvent = (eventName, callback) => {
  useEffect(() => {
    const cleanup = onEvent(eventName, callback);
    return cleanup;
  }, [eventName, callback]);
};

export const useWebSocketConnection = (baseUrl) => {
  useEffect(() => {
    initializeWebSocket(baseUrl);

    return () => {
      closeWebSocket();
    };
  }, [baseUrl]);
};
