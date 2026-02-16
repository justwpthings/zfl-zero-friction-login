import React, { useState } from 'react';

interface LoggedInViewProps {
  displayName: string;
  email: string;
  siteName: string;
  logoUrl: string | null;
  logoWidth: number;
  hideFooterCredit: boolean;
  onLogout: () => void;
  isLoggingOut: boolean;
}

export const LoggedInView: React.FC<LoggedInViewProps> = ({
  displayName,
  email,
  siteName,
  logoUrl,
  logoWidth,
  hideFooterCredit,
  onLogout,
  isLoggingOut,
}) => {
  const [showModal, setShowModal] = useState(false);

  const handleLogoutClick = () => {
    setShowModal(true);
  };

  const handleConfirmLogout = () => {
    setShowModal(false);
    onLogout();
  };

  const handleCancelLogout = () => {
    setShowModal(false);
  };

  return (
    <>
      <div className="w-full">
        <div className="zfl-card zfl-card-lg zfl-stack">
          <div className="text-center zfl-stack">
            {logoUrl && (
              <div>
                <img
                  src={logoUrl}
                  alt={siteName}
                  className="mx-auto w-auto"
                  style={{ maxWidth: `${logoWidth}px`, height: 'auto' }}
                />
              </div>
            )}
            <div
              className="mx-auto rounded-full flex items-center justify-center"
              style={{
                width: 'var(--zfl-icon-circle-size-md)',
                height: 'var(--zfl-icon-circle-size-md)',
                backgroundColor: 'var(--zfl-logged-in-icon-bg)'
              }}
            >
              <svg
                style={{
                  width: 'var(--zfl-icon-size-md)',
                  height: 'var(--zfl-icon-size-md)',
                  color: 'var(--zfl-logged-in-icon-color)'
                }}
                fill="none"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
            </div>

            <h1 className="zfl-heading-h1 font-bold">
              You're Already Logged In
            </h1>

            <p>
              Welcome back, <span className="font-semibold">{displayName}</span>!
            </p>

            <p className="zfl-text-sm zfl-text-secondary">
              {email}
            </p>

            <div className="zfl-notice zfl-notice-info">
              <p className="zfl-text-sm">
                You're currently signed in to {siteName}. You can continue browsing or sign out if you'd like to switch accounts.
              </p>
            </div>

            <button
              type="button"
              onClick={handleLogoutClick}
              disabled={isLoggingOut}
              className="zfl-button-destructive w-full font-semibold focus:outline-none"
            >
              {isLoggingOut ? 'Logging Out...' : 'Log Out'}
            </button>
          </div>
        </div>

        {!hideFooterCredit && (
          <div className="text-center" style={{ marginTop: 'var(--zfl-section-gap)' }}>
            <p className="zfl-footer">
              Secured by Zero Friction Login
            </p>
          </div>
        )}
      </div>

      {showModal && (
        <div
          className="fixed inset-0 zfl-modal-overlay flex items-center justify-center z-[99999]"
          style={{ padding: 'var(--zfl-section-gap)' }}
          onClick={handleCancelLogout}
        >
          <div
            className="zfl-modal max-w-md w-full animate-scale-in zfl-stack"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="text-center zfl-stack">
              <div
                className="mx-auto rounded-full flex items-center justify-center"
                style={{
                  width: 'var(--zfl-icon-circle-size-sm)',
                  height: 'var(--zfl-icon-circle-size-sm)',
                  backgroundColor: 'var(--zfl-modal-icon-bg)'
                }}
              >
                <svg
                  style={{
                    width: 'var(--zfl-icon-size-sm)',
                    height: 'var(--zfl-icon-size-sm)',
                    color: 'var(--zfl-modal-icon-color)'
                  }}
                  fill="none"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
              </div>

              <h2 className="zfl-heading-h2 font-bold">
                Confirm Logout
              </h2>

              <p className="zfl-text-secondary">
                Are you sure you want to log out? You'll need to sign in again to access your account.
              </p>
            </div>

            <div className="flex" style={{ gap: 'var(--zfl-form-gap)' }}>
              <button
                type="button"
                onClick={handleCancelLogout}
                disabled={isLoggingOut}
                className="zfl-button-secondary flex-1 font-medium focus:outline-none"
              >
                Cancel
              </button>

              <button
                type="button"
                onClick={handleConfirmLogout}
                disabled={isLoggingOut}
                className="zfl-button-destructive flex-1 font-medium focus:outline-none"
              >
                {isLoggingOut ? 'Logging Out...' : 'Yes, Log Out'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};
