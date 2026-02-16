import React, { useState, useEffect } from 'react';
import { LoginForm } from './LoginForm';
import { OTPInput } from './OTPInput';
import { LoggedInView } from './LoggedInView';
import { getConfig, requestAuth, verifyOTP, logout } from './api';
import type { Config, AuthResponse, VerifyOTPResponse } from './types';
import { hexToRgba } from './utils';

type ViewState = 'loading' | 'login' | 'otp' | 'magic-link-sent' | 'success';

interface Toast {
  type: 'success' | 'error' | 'info';
  message: string;
}

const getFontFamily = (font: string): string => {
  switch (font) {
    case 'roboto':
      return "'Roboto', sans-serif";
    case 'open_sans':
      return "'Open Sans', sans-serif";
    case 'lato':
      return "'Lato', sans-serif";
    case 'montserrat':
      return "'Montserrat', sans-serif";
    default:
      return 'inherit';
  }
};

const resolveValue = (value: string | undefined | null, fallback: string): string => {
  return value && value.length > 0 ? value : fallback;
};

export const App: React.FC = () => {
  const [viewState, setViewState] = useState<ViewState>('loading');
  const [config, setConfig] = useState<Config | null>(null);
  const [email, setEmail] = useState('');
  const [toast, setToast] = useState<Toast | null>(null);
  const [verifying, setVerifying] = useState(false);
  const [otpError, setOtpError] = useState('');
  const [loginError, setLoginError] = useState('');
  const [loginSuccess, setLoginSuccess] = useState('');
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [infoNotice, setInfoNotice] = useState('');

  useEffect(() => {
    loadConfig();
  }, []);

  useEffect(() => {
    if (toast) {
      const timer = setTimeout(() => {
        setToast(null);
      }, 5000);
      return () => clearTimeout(timer);
    }
  }, [toast]);

  useEffect(() => {
    if (config) {
      const fontsToLoad = new Set<string>();

      const fontMap: Record<string, string> = {
        'roboto': 'Roboto:wght@400;500;700',
        'open_sans': 'Open+Sans:wght@400;600;700',
        'lato': 'Lato:wght@400;700',
        'montserrat': 'Montserrat:wght@400;600;700'
      };

      if (config.heading_font && config.heading_font !== 'system_default' && fontMap[config.heading_font]) {
        fontsToLoad.add(fontMap[config.heading_font]);
      }

      if (config.body_font && config.body_font !== 'system_default' && fontMap[config.body_font]) {
        fontsToLoad.add(fontMap[config.body_font]);
      }

      if (fontsToLoad.size > 0) {
        const existingLink = document.getElementById('zfl-google-fonts');
        if (existingLink) {
          existingLink.remove();
        }

        const link = document.createElement('link');
        link.id = 'zfl-google-fonts';
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?${Array.from(fontsToLoad).join('&')}&display=swap`;
        document.head.appendChild(link);
      }
    }
  }, [config]);

  const loadConfig = async () => {
    try {
      const configData = await getConfig();
      setConfig(configData);
      setViewState('login');
    } catch (error) {
      showToast('error', 'Failed to load configuration. Please refresh the page.');
      setViewState('login');
    }
  };

  const showToast = (type: Toast['type'], message: string) => {
    setToast({ type, message });
  };

  const handleAuthRequested = (userEmail: string, response: AuthResponse) => {
    setEmail(userEmail);
    setLoginError('');
    setLoginSuccess('');
    setInfoNotice('');

    if (response.show_info_notice) {
      setInfoNotice(response.message);
    }

    if (response.method === 'magic_link') {
      setViewState('magic-link-sent');
      if (!response.show_info_notice) {
        showToast('success', 'Magic link sent! Check your email.');
      }
    } else {
      setViewState('otp');
      if (!response.show_info_notice) {
        showToast('success', 'Code sent! Check your email.');
      }
    }
  };

  const handleOTPComplete = async (otp: string) => {
    if (verifying) return;

    setVerifying(true);
    setOtpError('');

    try {
      const response = await verifyOTP(email, otp);

      if (response.success) {
        const verifyResponse = response as VerifyOTPResponse;
        setViewState('success');
        showToast('success', 'Login successful! Redirecting...');

        setTimeout(() => {
          if (verifyResponse.redirect_url) {
            window.location.href = verifyResponse.redirect_url;
          } else {
            window.location.reload();
          }
        }, 1500);
      } else {
        setOtpError(response.message);
        showToast('error', response.message);
      }
    } catch (error) {
      setOtpError('Verification failed. Please try again.');
      showToast('error', 'Verification failed. Please try again.');
    } finally {
      setVerifying(false);
    }
  };

  const handleResendOTP = async () => {
    try {
      const response = await requestAuth(email);

      if (response.success) {
        setOtpError('');
        showToast('success', 'New code sent! Check your email.');
      } else {
        showToast('error', response.message);
      }
    } catch (error) {
      showToast('error', 'Failed to resend code. Please try again.');
    }
  };

  const handleBackToLogin = () => {
    setViewState('login');
    setEmail('');
    setOtpError('');
  };

  const handleLogout = async () => {
    setIsLoggingOut(true);

    try {
      const response = await logout();

      if (response.success) {
        showToast('success', 'Logged out successfully!');

        setTimeout(() => {
          if (response.redirect_url) {
            window.location.href = response.redirect_url;
          } else {
            window.location.reload();
          }
        }, 500);
      } else {
        showToast('error', response.message);
        setIsLoggingOut(false);
      }
    } catch (error) {
      showToast('error', 'Failed to logout. Please try again.');
      setIsLoggingOut(false);
    }
  };

  const renderContent = () => {
    if (viewState === 'loading') {
      return (
        <div className="flex items-center justify-center">
          <div className="text-center zfl-stack" style={{ gap: 'calc(var(--zfl-section-gap) / 2)' }}>
            <div className="animate-spin zfl-spinner mx-auto"></div>
            <p className="zfl-text-secondary">Loading...</p>
          </div>
        </div>
      );
    }

    if (!config) {
      return (
        <div className="flex items-center justify-center">
          <div className="text-center zfl-stack" style={{ gap: 'calc(var(--zfl-section-gap) / 2)' }}>
            <div className="zfl-notice zfl-notice-error">
              <p className="zfl-text-sm">Failed to load configuration.</p>
            </div>
            <button onClick={loadConfig} className="zfl-button-primary zfl-text-sm">
              Retry
            </button>
          </div>
        </div>
      );
    }

    if (config.is_logged_in && config.current_user) {
      return (
        <LoggedInView
          displayName={config.current_user.display_name}
          email={config.current_user.email}
          siteName={config.site_name}
          logoUrl={config.logo_url}
          logoWidth={config.logo_width}
          hideFooterCredit={config.hide_footer_credit}
          onLogout={handleLogout}
          isLoggingOut={isLoggingOut}
        />
      );
    }

    if (viewState === 'login') {
      return (
        <LoginForm
          config={config}
          onAuthRequested={handleAuthRequested}
          onError={(message) => setLoginError(message)}
          errorMessage={loginError}
          successMessage={loginSuccess}
        />
      );
    }

    if (viewState === 'otp') {
      return (
        <div className="zfl-card">
          <div className="text-center zfl-stack" style={{ gap: 'calc(var(--zfl-section-gap) / 2)' }}>
            <h2 className="zfl-heading-h2">
              Enter Verification Code
            </h2>
          </div>

          <OTPInput
            length={config.otp_length}
            type={config.otp_type}
            onComplete={handleOTPComplete}
            expirySeconds={config.expiry_seconds}
            onResend={handleResendOTP}
            email={email}
            loading={verifying}
            error={otpError}
            infoNotice={infoNotice}
          />

          <div className="text-center" style={{ marginTop: 'var(--zfl-section-gap)' }}>
            <button
              onClick={handleBackToLogin}
              disabled={verifying}
              className="zfl-button-secondary zfl-text-sm"
            >
              Back to login
            </button>
          </div>
        </div>
      );
    }

    if (viewState === 'magic-link-sent') {
      return (
        <div className="zfl-card">
          <div className="text-center zfl-stack">
            <div
              className="mx-auto rounded-full flex items-center justify-center"
              style={{
                width: 'var(--zfl-icon-circle-size-sm)',
                height: 'var(--zfl-icon-circle-size-sm)',
                backgroundColor: 'var(--zfl-magic-icon-bg)'
              }}
            >
              <svg
                style={{
                  width: 'var(--zfl-icon-size-sm)',
                  height: 'var(--zfl-icon-size-sm)',
                  color: 'var(--zfl-magic-icon-color)'
                }}
                fill="none"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
            </div>

            <h2 className="zfl-heading-h2">
              Check Your Email
            </h2>

            {infoNotice && (
              <div className="zfl-notice zfl-notice-info">
                <p className="zfl-text-sm text-center">{infoNotice}</p>
              </div>
            )}

            <p className="zfl-text-sm zfl-text-secondary">
              Click the link in the email to sign in instantly. The link will expire in{' '}
              {Math.floor(config.expiry_seconds / 60)} minutes.
            </p>

            <div style={{ paddingTop: 'var(--zfl-section-gap)' }}>
              <button
                onClick={handleBackToLogin}
                className="zfl-button-secondary zfl-text-sm"
              >
                Back to login
              </button>
            </div>
          </div>
        </div>
      );
    }

    if (viewState === 'success') {
      return (
        <div className="zfl-card">
          <div className="text-center zfl-stack">
            <div
              className="mx-auto rounded-full flex items-center justify-center"
              style={{
                width: 'var(--zfl-icon-circle-size-sm)',
                height: 'var(--zfl-icon-circle-size-sm)',
                backgroundColor: 'var(--zfl-success-icon-bg)'
              }}
            >
              <svg
                style={{
                  width: 'var(--zfl-icon-size-sm)',
                  height: 'var(--zfl-icon-size-sm)',
                  color: 'var(--zfl-success-icon-color)'
                }}
                fill="none"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path d="M5 13l4 4L19 7"></path>
              </svg>
            </div>

            <h2 className="zfl-heading-h2">
              Success!
            </h2>

            <p className="zfl-text-secondary">You've been logged in successfully.</p>

            <div className="animate-spin zfl-spinner-sm mx-auto"></div>

            <p className="zfl-text-sm zfl-text-secondary">Redirecting...</p>
          </div>
        </div>
      );
    }

    return null;
  };

  const modalOverlayColor = resolveValue(config?.modal_overlay_color, '#000000');
  const modalOverlayOpacityRaw = parseFloat(resolveValue(config?.modal_overlay_opacity, '0.5'));
  const modalOverlayOpacity = Number.isNaN(modalOverlayOpacityRaw) ? 0.5 : modalOverlayOpacityRaw;
  const modalOverlayBg = hexToRgba(modalOverlayColor, modalOverlayOpacity);

  const styleVars: React.CSSProperties = {
    '--zfl-font-body': getFontFamily(config?.body_font ?? 'system_default'),
    '--zfl-font-heading': getFontFamily(config?.heading_font ?? 'system_default'),
    '--zfl-font-size-base': resolveValue(config?.font_size_base, '16px'),
    '--zfl-font-size-sm': resolveValue(config?.font_size_sm, '14px'),
    '--zfl-font-size-xs': resolveValue(config?.font_size_xs, '12px'),
    '--zfl-heading-size-h1': resolveValue(config?.heading_size_h1, '24px'),
    '--zfl-heading-size-h2': resolveValue(config?.heading_size_h2, '20px'),
    '--zfl-line-height-base': resolveValue(config?.line_height_base, '1.5'),
    '--zfl-text-primary': resolveValue(config?.text_primary_color, '#111827'),
    '--zfl-text-secondary': resolveValue(config?.text_secondary_color, '#6b7280'),
    '--zfl-text-muted': resolveValue(config?.text_muted_color, '#4b5563'),
    '--zfl-text-label': resolveValue(config?.text_label_color, '#374151'),
    '--zfl-text-inverse': resolveValue(config?.text_inverse_color, '#ffffff'),
    '--zfl-heading-color': resolveValue(config?.active_tab_text_color, '#111827'),
    '--zfl-card-bg': resolveValue(config?.card_background, '#ffffff'),
    '--zfl-overlay-bg': resolveValue(config?.overlay_background, '#f0f0f0'),
    '--zfl-card-radius': resolveValue(config?.card_radius, '8px'),
    '--zfl-card-shadow': resolveValue(config?.card_shadow, '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)'),
    '--zfl-card-padding': resolveValue(config?.card_padding, '20px'),
    '--zfl-logged-in-card-padding': resolveValue(config?.logged_in_card_padding, '32px'),
    '--zfl-modal-padding': resolveValue(config?.modal_padding, '24px'),
    '--zfl-form-gap': resolveValue(config?.form_gap, '16px'),
    '--zfl-section-gap': resolveValue(config?.section_gap, '16px'),
    '--zfl-input-bg': resolveValue(config?.input_background, '#ffffff'),
    '--zfl-input-text': resolveValue(config?.input_text_color, '#111827'),
    '--zfl-input-placeholder': resolveValue(config?.input_placeholder_color, '#9ca3af'),
    '--zfl-input-border': resolveValue(config?.input_border_color, '#d1d5db'),
    '--zfl-input-border-focus': resolveValue(config?.input_border_color_focus, '#9ca3af'),
    '--zfl-input-border-width': resolveValue(config?.input_border_width, '1px'),
    '--zfl-input-radius': resolveValue(config?.input_radius, '8px'),
    '--zfl-input-focus-ring-color': resolveValue(config?.input_focus_ring_color, '#b2d5e5'),
    '--zfl-input-focus-ring-width': resolveValue(config?.input_focus_ring_width, '3px'),
    '--zfl-input-disabled-bg': resolveValue(config?.input_disabled_bg, '#f3f4f6'),
    '--zfl-input-disabled-text': resolveValue(config?.input_disabled_text, '#9ca3af'),
    '--zfl-input-error-border': resolveValue(config?.input_error_border, '#ef4444'),
    '--zfl-input-padding-x': resolveValue(config?.input_padding_x, '12px'),
    '--zfl-input-padding-y': resolveValue(config?.input_padding_y, '10px'),
    '--zfl-otp-bg': resolveValue(config?.otp_box_bg, '#ffffff'),
    '--zfl-otp-text': resolveValue(config?.otp_box_text_color, '#111827'),
    '--zfl-otp-border': resolveValue(config?.otp_box_border_color, '#d1d5db'),
    '--zfl-otp-border-filled': resolveValue(config?.otp_box_border_color_filled, '#9ca3af'),
    '--zfl-otp-border-active': resolveValue(config?.otp_box_border_color_active, '#0073aa'),
    '--zfl-otp-border-error': resolveValue(config?.otp_box_border_color_error, '#ef4444'),
    '--zfl-otp-border-width': resolveValue(config?.otp_box_border_width, '2px'),
    '--zfl-otp-radius': resolveValue(config?.otp_box_radius, '8px'),
    '--zfl-otp-box-size-6-w': resolveValue(config?.otp_box_size_6_w, '44px'),
    '--zfl-otp-box-size-6-h': resolveValue(config?.otp_box_size_6_h, '48px'),
    '--zfl-otp-box-size-8-w': resolveValue(config?.otp_box_size_8_w, '48px'),
    '--zfl-otp-box-size-8-h': resolveValue(config?.otp_box_size_8_h, '56px'),
    '--zfl-otp-font-size': resolveValue(config?.otp_box_font_size, '20px'),
    '--zfl-otp-gap': resolveValue(config?.otp_box_gap, '8px'),
    '--zfl-otp-disabled-bg': resolveValue(config?.otp_box_disabled_bg, '#f3f4f6'),
    '--zfl-button-bg': resolveValue(config?.button_background, '#0073aa'),
    '--zfl-button-text': resolveValue(config?.button_text_color, '#ffffff'),
    '--zfl-button-hover-bg': resolveValue(config?.button_hover_background, '#006799'),
    '--zfl-button-active-bg': resolveValue(config?.button_active_background, '#006190'),
    '--zfl-button-disabled-bg': resolveValue(config?.button_disabled_background, '#d1d5db'),
    '--zfl-button-disabled-text': resolveValue(config?.button_disabled_text, '#6b7280'),
    '--zfl-button-radius': resolveValue(config?.button_radius, '8px'),
    '--zfl-button-padding-x': resolveValue(config?.button_padding_x, '16px'),
    '--zfl-button-padding-y': resolveValue(config?.button_padding_y, '10px'),
    '--zfl-secondary-button-bg': resolveValue(config?.secondary_button_background, '#f3f4f6'),
    '--zfl-secondary-button-text': resolveValue(config?.secondary_button_text_color, '#374151'),
    '--zfl-secondary-button-hover-bg': resolveValue(config?.secondary_button_hover_background, '#dadbdd'),
    '--zfl-secondary-button-active-bg': resolveValue(config?.secondary_button_active_background, '#cecfd1'),
    '--zfl-destructive-button-bg': resolveValue(config?.destructive_button_background, '#dc2626'),
    '--zfl-destructive-button-text': resolveValue(config?.destructive_button_text, '#ffffff'),
    '--zfl-destructive-button-hover-bg': resolveValue(config?.destructive_button_hover_background, '#dc2626'),
    '--zfl-destructive-button-active-bg': resolveValue(config?.destructive_button_active_background, '#dc2626'),
    '--zfl-destructive-button-disabled-bg': resolveValue(config?.destructive_button_disabled_background, '#d1d5db'),
    '--zfl-destructive-button-disabled-text': resolveValue(config?.destructive_button_disabled_text, '#6b7280'),
    '--zfl-link-color': resolveValue(config?.link_color, '#2563eb'),
    '--zfl-link-hover-color': resolveValue(config?.link_hover_color, '#1d4ed8'),
    '--zfl-link-decoration': resolveValue(config?.link_decoration, 'underline'),
    '--zfl-link-hover-decoration': resolveValue(config?.link_hover_decoration, 'underline'),
    '--zfl-tab-bg': resolveValue(config?.tab_background, '#ffffff'),
    '--zfl-tab-text': resolveValue(config?.tab_text_color, '#6b7280'),
    '--zfl-tab-active-bg': resolveValue(config?.active_tab_background, '#f9fafb'),
    '--zfl-tab-active-text': resolveValue(config?.active_tab_text_color, '#111827'),
    '--zfl-tab-border-width': resolveValue(config?.tab_border_width, '2px'),
    '--zfl-tab-border-inactive': resolveValue(config?.tab_border_color_inactive, 'transparent'),
    '--zfl-tab-border-active': resolveValue(config?.tab_border_color_active, '#0073aa'),
    '--zfl-tab-radius': resolveValue(config?.tab_radius, '8px'),
    '--zfl-tab-padding-x': resolveValue(config?.tab_padding_x, '16px'),
    '--zfl-tab-padding-y': resolveValue(config?.tab_padding_y, '10px'),
    '--zfl-notice-radius': resolveValue(config?.notice_radius, '8px'),
    '--zfl-notice-padding': resolveValue(config?.notice_padding, '12px'),
    '--zfl-notice-border-width': resolveValue(config?.notice_border_width, '1px'),
    '--zfl-notice-error-bg': resolveValue(config?.notice_error_bg, '#fef2f2'),
    '--zfl-notice-error-border': resolveValue(config?.notice_error_border, '#fecaca'),
    '--zfl-notice-error-text': resolveValue(config?.notice_error_text, '#991b1b'),
    '--zfl-notice-success-bg': resolveValue(config?.notice_success_bg, '#f0fdf4'),
    '--zfl-notice-success-border': resolveValue(config?.notice_success_border, '#bbf7d0'),
    '--zfl-notice-success-text': resolveValue(config?.notice_success_text, '#166534'),
    '--zfl-notice-info-bg': resolveValue(config?.notice_info_bg, '#eff6ff'),
    '--zfl-notice-info-border': resolveValue(config?.notice_info_border, '#bfdbfe'),
    '--zfl-notice-info-text': resolveValue(config?.notice_info_text, '#1e40af'),
    '--zfl-toast-success-bg': resolveValue(config?.toast_success_bg, '#16a34a'),
    '--zfl-toast-error-bg': resolveValue(config?.toast_error_bg, '#dc2626'),
    '--zfl-toast-info-bg': resolveValue(config?.toast_info_bg, '#2563eb'),
    '--zfl-toast-text': resolveValue(config?.toast_text_color, '#ffffff'),
    '--zfl-toast-radius': resolveValue(config?.toast_radius, '8px'),
    '--zfl-toast-shadow': resolveValue(config?.toast_shadow, '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)'),
    '--zfl-toast-padding-x': resolveValue(config?.toast_padding_x, '24px'),
    '--zfl-toast-padding-y': resolveValue(config?.toast_padding_y, '16px'),
    '--zfl-toast-max-width': resolveValue(config?.toast_max_width, '448px'),
    '--zfl-toast-close-color': resolveValue(config?.toast_close_color, '#ffffff'),
    '--zfl-toast-close-hover-color': resolveValue(config?.toast_close_hover_color, '#e5e7eb'),
    '--zfl-toast-close-bg': resolveValue(config?.toast_close_bg, 'transparent'),
    '--zfl-toast-close-hover-bg': resolveValue(config?.toast_close_hover_bg, 'transparent'),
    '--zfl-magic-icon-bg': resolveValue(config?.magic_icon_bg, '#dbeafe'),
    '--zfl-magic-icon-color': resolveValue(config?.magic_icon_color, '#2563eb'),
    '--zfl-success-icon-bg': resolveValue(config?.success_icon_bg, '#dcfce7'),
    '--zfl-success-icon-color': resolveValue(config?.success_icon_color, '#16a34a'),
    '--zfl-logged-in-icon-bg': resolveValue(config?.logged_in_icon_bg, '#dcfce7'),
    '--zfl-logged-in-icon-color': resolveValue(config?.logged_in_icon_color, '#16a34a'),
    '--zfl-modal-icon-bg': resolveValue(config?.modal_icon_bg, '#fee2e2'),
    '--zfl-modal-icon-color': resolveValue(config?.modal_icon_color, '#dc2626'),
    '--zfl-icon-circle-size-sm': resolveValue(config?.icon_circle_size_sm, '64px'),
    '--zfl-icon-circle-size-md': resolveValue(config?.icon_circle_size_md, '80px'),
    '--zfl-icon-size-sm': resolveValue(config?.icon_size_sm, '32px'),
    '--zfl-icon-size-md': resolveValue(config?.icon_size_md, '40px'),
    '--zfl-modal-bg': resolveValue(config?.modal_background, '#ffffff'),
    '--zfl-modal-text-color': resolveValue(config?.modal_text_color, '#111827'),
    '--zfl-modal-radius': resolveValue(config?.modal_radius, '8px'),
    '--zfl-modal-shadow': resolveValue(config?.modal_shadow, '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)'),
    '--zfl-modal-overlay-color': resolveValue(config?.modal_overlay_color, '#000000'),
    '--zfl-modal-overlay-opacity': resolveValue(config?.modal_overlay_opacity, '0.5'),
    '--zfl-modal-overlay-bg': modalOverlayBg,
    '--zfl-spinner-color': resolveValue(config?.spinner_color, '#2563eb'),
    '--zfl-spinner-success-color': resolveValue(config?.spinner_success_color, '#0073aa'),
    '--zfl-spinner-size-lg': resolveValue(config?.spinner_size_lg, '48px'),
    '--zfl-spinner-size-sm': resolveValue(config?.spinner_size_sm, '32px'),
    '--zfl-spinner-border-width': resolveValue(config?.spinner_border_width, '2px'),
    '--zfl-footer-text-color': resolveValue(config?.footer_text_color, '#6b7280'),
    '--zfl-footer-font-size': resolveValue(config?.footer_font_size, '12px'),
    '--zfl-animation-slide-in-duration': resolveValue(config?.animation_slide_in_duration, '300ms'),
    '--zfl-animation-scale-in-duration': resolveValue(config?.animation_scale_in_duration, '200ms'),
    '--zfl-transition-duration': resolveValue(config?.transition_duration, '200ms'),
    '--zfl-primary-color': resolveValue(config?.primary_color, '#0073aa')
  } as React.CSSProperties;

  const rootClassName = config?.animation_enabled === false ? 'zfl-root zfl-animations-disabled' : 'zfl-root';
  const rootStyle: React.CSSProperties = {
    ...styleVars,
    backgroundColor: 'var(--zfl-overlay-bg)',
    borderRadius: 'var(--zfl-card-radius)',
    padding: 'var(--zfl-section-gap)'
  };

  return (
    <div className={rootClassName} style={rootStyle}>
      {renderContent()}

      {toast && (
        <div
          className="zfl-toast-container animate-slide-in"
          role="status"
          aria-live="polite"
        >
          <div
            className={`zfl-toast ${
              toast.type === 'success'
                ? 'zfl-toast-success'
                : toast.type === 'error'
                ? 'zfl-toast-error'
                : 'zfl-toast-info'
            }`}
          >
            <div className="zfl-toast-content">
              <svg
                className="zfl-toast-icon"
                fill="none"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
              >
                {toast.type === 'success' && <path d="M5 13l4 4L19 7"></path>}
                {toast.type === 'error' && <path d="M6 18L18 6M6 6l12 12"></path>}
                {toast.type === 'info' && <path d="M13 16h-1v-4h-1m1-4h.01M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path>}
              </svg>
              <p className="zfl-toast-message zfl-text-sm font-medium">{toast.message}</p>
              <button
                onClick={() => setToast(null)}
                className="zfl-toast-close"
                type="button"
                aria-label="Close notification"
              >
                <svg
                  className="w-5 h-5"
                  fill="none"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
