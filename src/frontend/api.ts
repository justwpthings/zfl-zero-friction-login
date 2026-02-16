import type { Config, AuthResponse, VerifyOTPResponse, ErrorResponse, LogoutResponse } from './types';

const getApiUrl = (endpoint: string): string => {
  const hasZflData = typeof window.zflData !== 'undefined';
  const apiUrl = hasZflData && window.zflData.apiUrl ? window.zflData.apiUrl : null;

  if (apiUrl) {
    const baseUrl = apiUrl.endsWith('/') ? apiUrl.slice(0, -1) : apiUrl;
    const path = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    return `${baseUrl}${path}`;
  }

  return `/wp-json/zfl/v1${endpoint}`;
};

const getHeaders = (): HeadersInit => {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (typeof window.zflData !== 'undefined' && window.zflData.nonce) {
    headers['X-WP-Nonce'] = window.zflData.nonce;
  }

  return headers;
};

export const getConfig = async (): Promise<Config> => {
  try {
    const url = getApiUrl('/config');

    const response = await fetch(url, {
      method: 'GET',
      headers: getHeaders(),
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return response.json();
  } catch (error) {
    throw error;
  }
};

export const requestAuth = async (email: string, displayName?: string): Promise<AuthResponse | ErrorResponse> => {
  try {
    const body: { email: string; display_name?: string } = { email };
    if (displayName) {
      body.display_name = displayName;
    }

    const response = await fetch(getApiUrl('/request-auth'), {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });

    const data = await response.json();

    if (!response.ok) {
      let message = data.message || `Request failed with status ${response.status}`;
      if (data.error_detail) {
        message += ` - ${data.error_detail}`;
      }
      return {
        success: false,
        message,
        reason: data.reason,
      };
    }

    return data;
  } catch (error) {
    return {
      success: false,
      message: 'Network error. Please try again.',
    };
  }
};

export const verifyOTP = async (email: string, otp: string): Promise<VerifyOTPResponse | ErrorResponse> => {
  try {
    const response = await fetch(getApiUrl('/verify-otp'), {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'same-origin',
      body: JSON.stringify({ email, otp }),
    });

    const data = await response.json();

    if (!response.ok) {
      return {
        success: false,
        message: data.message || `Verification failed with status ${response.status}`,
      };
    }

    return data;
  } catch (error) {
    return {
      success: false,
      message: 'Network error. Please try again.',
    };
  }
};

export const logout = async (): Promise<LogoutResponse> => {
  try {
    const response = await fetch(getApiUrl('/logout'), {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'same-origin',
    });

    const data = await response.json();

    if (!response.ok) {
      return {
        success: false,
        message: data.message || `Logout failed with status ${response.status}`,
      };
    }

    return data;
  } catch (error) {
    return {
      success: false,
      message: 'Network error. Please try again.',
    };
  }
};
