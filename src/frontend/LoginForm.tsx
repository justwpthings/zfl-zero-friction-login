import React, { useState, FormEvent, useEffect } from 'react';
import { requestAuth } from './api';
import type { Config, AuthResponse, ErrorResponse } from './types';
interface LoginFormProps {
  config: Config;
  onAuthRequested: (email: string, response: AuthResponse) => void;
  onError: (message: string) => void;
  errorMessage?: string;
  successMessage?: string;
}

export const LoginForm: React.FC<LoginFormProps> = ({ config, onAuthRequested, onError, errorMessage, successMessage }) => {
  const [activeTab, setActiveTab] = useState<'login' | 'register'>('login');
  const [email, setEmail] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [loading, setLoading] = useState(false);
  const [countdown, setCountdown] = useState(0);

  useEffect(() => {
    if (countdown <= 0) return;

    const timer = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [countdown]);

  useEffect(() => {
    if (!config.allow_registration && activeTab === 'register') {
      setActiveTab('login');
    }
  }, [config.allow_registration, activeTab]);

  const validateEmail = (email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();

    if (!validateEmail(email)) {
      onError('Please enter a valid email address.');
      return;
    }

    if (activeTab === 'register' && !displayName.trim()) {
      onError('Please enter your display name.');
      return;
    }

    if (countdown > 0) {
      return;
    }

    setLoading(true);

    try {
      const response = await requestAuth(email, activeTab === 'register' ? displayName : undefined);

      if (response.success) {
        setCountdown(30);
        onAuthRequested(email, response as AuthResponse);
      } else {
        onError((response as ErrorResponse).message);
      }
    } catch (error) {
      onError('Failed to send authentication request. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const getButtonText = (): string => {
    if (loading) return 'Sending...';
    if (countdown > 0) return `Resend in ${countdown}s`;

    if (config.login_method === 'magic_link') {
      return 'Send Magic Link';
    }

    return 'Send Code';
  };

  const getDescriptionText = (): string => {
    if (config.login_method === 'magic_link') {
      return 'We\'ll send you a magic link to sign in instantly.';
    }

    const lengthText = config.otp_length === 6 ? '6' : '8';
    const typeText = config.otp_type === 'numeric' ? 'digit' : 'character';

    return `We'll send you a ${lengthText}-${typeText} code to verify your identity.`;
  };

  return (
    <div className="w-full">
      <div className="zfl-card zfl-stack">
        <div className="text-center zfl-stack" style={{ gap: 'calc(var(--zfl-section-gap) / 2)' }}>
          {config.logo_url && (
            <div>
              <img
                src={config.logo_url}
                alt={config.site_name}
                className="mx-auto w-auto"
                style={{ maxWidth: `${config.logo_width}px`, height: 'auto' }}
              />
            </div>
          )}
          <h1 className="zfl-heading-h1 font-bold">
            Welcome to {config.site_name}
          </h1>
          <p className="zfl-text-sm zfl-text-secondary">
            {getDescriptionText()}
          </p>
        </div>

        {config.allow_registration && (
          <div className="flex" style={{ gap: 'calc(var(--zfl-form-gap) / 2)' }}>
            <button
              type="button"
              onClick={() => setActiveTab('login')}
              className={`zfl-tab flex-1 zfl-text-sm font-medium text-center ${activeTab === 'login' ? 'zfl-tab-active' : ''}`}
            >
              Login
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('register')}
              className={`zfl-tab flex-1 zfl-text-sm font-medium text-center ${activeTab === 'register' ? 'zfl-tab-active' : ''}`}
            >
              Register
            </button>
          </div>
        )}

        {errorMessage && (
          <div className="zfl-notice zfl-notice-error">
            <p className="zfl-text-sm">{errorMessage}</p>
          </div>
        )}

        {successMessage && (
          <div className="zfl-notice zfl-notice-success">
            <p className="zfl-text-sm">{successMessage}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="zfl-form">
          {activeTab === 'register' && (
            <div>
              <label htmlFor="displayName" className="block zfl-text-sm font-medium zfl-text-label" style={{ marginBottom: 'calc(var(--zfl-form-gap) / 4)' }}>
                Display Name
              </label>
              <input
                type="text"
                id="displayName"
                value={displayName}
                onChange={(e) => setDisplayName(e.target.value)}
                placeholder="John Doe"
                required
                disabled={loading || countdown > 0}
                className="zfl-input w-full focus:outline-none"
              />
            </div>
          )}

          <div>
            <label htmlFor="email" className="block zfl-text-sm font-medium zfl-text-label" style={{ marginBottom: 'calc(var(--zfl-form-gap) / 4)' }}>
              Email Address
            </label>
            <input
              type="email"
              id="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              required
              disabled={loading || countdown > 0}
              className="zfl-input w-full focus:outline-none"
            />
          </div>

          <button
            type="submit"
            disabled={loading || countdown > 0 || !email}
            className="zfl-button-primary w-full font-semibold focus:outline-none"
          >
            {getButtonText()}
          </button>
        </form>

        {config.show_policy_links && (config.terms_page_url || config.privacy_page_url) && (
          <div style={{ marginTop: 'var(--zfl-section-gap)', paddingTop: 'var(--zfl-section-gap)', borderTop: '1px solid var(--zfl-input-border)' }}>
            <p className="zfl-text-xs text-center zfl-text-muted">
              {activeTab === 'login' ? 'By logging in, you agree to our ' : 'By registering, you agree to our '}
              {config.terms_page_url && (
                <>
                  <a
                    href={config.terms_page_url}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    Terms of Service
                  </a>
                  {config.privacy_page_url && ' and '}
                </>
              )}
              {config.privacy_page_url && (
                <a
                  href={config.privacy_page_url}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Privacy Policy
                </a>
              )}
              .
            </p>
          </div>
        )}
      </div>

      {!config.hide_footer_credit && (
        <div className="text-center" style={{ marginTop: 'var(--zfl-section-gap)' }}>
          <p className="zfl-footer">
            Secured by Zero Friction Login
          </p>
        </div>
      )}
    </div>
  );
};
