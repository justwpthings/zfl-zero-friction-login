import React, { useState, useRef, useEffect, KeyboardEvent, ClipboardEvent } from 'react';
import type { OTPType } from './types';

interface OTPInputProps {
  length: number;
  type: OTPType;
  onComplete: (otp: string) => void;
  expirySeconds: number;
  onResend: () => void;
  email: string;
  loading?: boolean;
  error?: string;
  infoNotice?: string;
}

export const OTPInput: React.FC<OTPInputProps> = ({
  length,
  type,
  onComplete,
  expirySeconds,
  onResend,
  email,
  loading = false,
  error = '',
  infoNotice = '',
}) => {
  const [otp, setOtp] = useState<string[]>(Array(length).fill(''));
  const [activeIndex, setActiveIndex] = useState<number>(0);
  const [timeLeft, setTimeLeft] = useState<number>(expirySeconds);
  const [resendCooldown, setResendCooldown] = useState<number>(0);
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    if (inputRefs.current[0]) {
      inputRefs.current[0].focus();
    }
  }, []);

  useEffect(() => {
    if (timeLeft <= 0) return;

    const timer = setInterval(() => {
      setTimeLeft((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [timeLeft]);

  useEffect(() => {
    if (resendCooldown <= 0) return;

    const timer = setInterval(() => {
      setResendCooldown((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [resendCooldown]);

  const isValidChar = (char: string): boolean => {
    if (type === 'numeric') {
      return /^\d$/.test(char);
    }
    return /^[a-zA-Z0-9]$/.test(char);
  };

  const handleChange = (index: number, value: string) => {
    if (value && !isValidChar(value)) return;

    const newOtp = [...otp];
    newOtp[index] = value.toUpperCase();
    setOtp(newOtp);

    if (value && index < length - 1) {
      inputRefs.current[index + 1]?.focus();
      setActiveIndex(index + 1);
    }

    if (newOtp.every((digit) => digit !== '')) {
      onComplete(newOtp.join(''));
    }
  };

  const handleKeyDown = (index: number, e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace') {
      e.preventDefault();
      const newOtp = [...otp];

      if (otp[index]) {
        newOtp[index] = '';
        setOtp(newOtp);
      } else if (index > 0) {
        newOtp[index - 1] = '';
        setOtp(newOtp);
        inputRefs.current[index - 1]?.focus();
        setActiveIndex(index - 1);
      }
    } else if (e.key === 'ArrowLeft' && index > 0) {
      inputRefs.current[index - 1]?.focus();
      setActiveIndex(index - 1);
    } else if (e.key === 'ArrowRight' && index < length - 1) {
      inputRefs.current[index + 1]?.focus();
      setActiveIndex(index + 1);
    }
  };

  const handlePaste = (e: ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData('text/plain').trim();

    const validChars = pastedData
      .split('')
      .filter(isValidChar)
      .slice(0, length);

    if (validChars.length > 0) {
      const newOtp = [...otp];
      validChars.forEach((char, idx) => {
        if (idx < length) {
          newOtp[idx] = char.toUpperCase();
        }
      });
      setOtp(newOtp);

      const nextIndex = Math.min(validChars.length, length - 1);
      inputRefs.current[nextIndex]?.focus();
      setActiveIndex(nextIndex);

      if (validChars.length === length) {
        onComplete(newOtp.join(''));
      }
    }
  };

  const handleFocus = (index: number) => {
    setActiveIndex(index);
  };

  const handleResend = () => {
    if (resendCooldown > 0) return;

    setOtp(Array(length).fill(''));
    setTimeLeft(expirySeconds);
    setResendCooldown(30);
    setActiveIndex(0);
    inputRefs.current[0]?.focus();
    onResend();
  };

  const formatTime = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const isComplete = otp.every((digit) => digit !== '');

  return (
    <div className="w-full max-w-md mx-auto zfl-stack">
      <div className="text-center zfl-stack" style={{ gap: 'calc(var(--zfl-section-gap) / 4)' }}>
        <p className="zfl-text-sm zfl-text-secondary">
          Enter the code sent to <span className="font-medium">{email}</span>
        </p>
        {timeLeft > 0 ? (
          <p className="zfl-text-xs zfl-text-muted">
            Code expires in {formatTime(timeLeft)}
          </p>
        ) : (
          <p className="zfl-text-xs font-medium zfl-text-error">
            Code expired. Please request a new one.
          </p>
        )}
      </div>

      {infoNotice && (
        <div className="zfl-notice zfl-notice-info">
          <p className="zfl-text-sm text-center">{infoNotice}</p>
        </div>
      )}

      {error && (
        <div className="zfl-notice zfl-notice-error">
          <p className="zfl-text-sm text-center">{error}</p>
        </div>
      )}

      <div className="flex flex-col items-center" style={{ gap: 'calc(var(--zfl-section-gap) / 2)' }}>
        {length === 8 ? (
          <>
            <div className="flex justify-center" style={{ gap: 'var(--zfl-otp-gap)' }}>
              {otp.slice(0, 4).map((digit, index) => (
                <input
                  key={index}
                  ref={(el) => (inputRefs.current[index] = el)}
                  type="text"
                  inputMode={type === 'numeric' ? 'numeric' : 'text'}
                  maxLength={1}
                  value={digit}
                  onChange={(e) => handleChange(index, e.target.value)}
                  onKeyDown={(e) => handleKeyDown(index, e)}
                  onPaste={handlePaste}
                  onFocus={() => handleFocus(index)}
                  disabled={loading || timeLeft <= 0}
                  style={{
                    width: 'var(--zfl-otp-box-size-8-w)',
                    height: 'var(--zfl-otp-box-size-8-h)',
                    fontSize: 'min(var(--zfl-otp-font-size), calc(var(--zfl-otp-box-size-8-h) * 0.55))',
                    borderColor: error
                      ? 'var(--zfl-otp-border-error)'
                      : activeIndex === index
                      ? 'var(--zfl-otp-border-active)'
                      : digit
                      ? 'var(--zfl-otp-border-filled)'
                      : 'var(--zfl-otp-border)',
                  } as React.CSSProperties}
                  className="zfl-otp-input text-center font-semibold focus:outline-none disabled:cursor-not-allowed"
                />
              ))}
            </div>
            <div className="flex justify-center" style={{ gap: 'var(--zfl-otp-gap)' }}>
              {otp.slice(4, 8).map((digit, index) => {
                const actualIndex = index + 4;
                return (
                  <input
                    key={actualIndex}
                    ref={(el) => (inputRefs.current[actualIndex] = el)}
                    type="text"
                    inputMode={type === 'numeric' ? 'numeric' : 'text'}
                    maxLength={1}
                    value={digit}
                    onChange={(e) => handleChange(actualIndex, e.target.value)}
                    onKeyDown={(e) => handleKeyDown(actualIndex, e)}
                    onPaste={handlePaste}
                    onFocus={() => handleFocus(actualIndex)}
                    disabled={loading || timeLeft <= 0}
                    style={{
                      width: 'var(--zfl-otp-box-size-8-w)',
                      height: 'var(--zfl-otp-box-size-8-h)',
                      fontSize: 'min(var(--zfl-otp-font-size), calc(var(--zfl-otp-box-size-8-h) * 0.55))',
                      borderColor: error
                        ? 'var(--zfl-otp-border-error)'
                        : activeIndex === actualIndex
                        ? 'var(--zfl-otp-border-active)'
                        : digit
                        ? 'var(--zfl-otp-border-filled)'
                        : 'var(--zfl-otp-border)',
                    } as React.CSSProperties}
                    className="zfl-otp-input text-center font-semibold focus:outline-none disabled:cursor-not-allowed"
                  />
                );
              })}
            </div>
          </>
        ) : (
          <div className="zfl-otp-grid-6 flex justify-center" style={{ gap: 'var(--zfl-otp-gap)' }}>
            {otp.map((digit, index) => (
              <input
                key={index}
                ref={(el) => (inputRefs.current[index] = el)}
                type="text"
                inputMode={type === 'numeric' ? 'numeric' : 'text'}
                maxLength={1}
                value={digit}
                onChange={(e) => handleChange(index, e.target.value)}
                onKeyDown={(e) => handleKeyDown(index, e)}
                onPaste={handlePaste}
                onFocus={() => handleFocus(index)}
                disabled={loading || timeLeft <= 0}
                style={{
                  width: 'var(--zfl-otp-box-size-6-w)',
                  height: 'var(--zfl-otp-box-size-6-h)',
                  fontSize: 'min(var(--zfl-otp-font-size), calc(var(--zfl-otp-box-size-6-h) * 0.55))',
                  borderColor: error
                    ? 'var(--zfl-otp-border-error)'
                    : activeIndex === index
                    ? 'var(--zfl-otp-border-active)'
                    : digit
                    ? 'var(--zfl-otp-border-filled)'
                    : 'var(--zfl-otp-border)',
                } as React.CSSProperties}
                className="zfl-otp-input text-center font-semibold focus:outline-none disabled:cursor-not-allowed"
              />
            ))}
          </div>
        )}
      </div>

      <div className="flex flex-col items-center" style={{ gap: 'calc(var(--zfl-section-gap) / 4)' }}>
        <button
          type="button"
          onClick={handleResend}
          disabled={resendCooldown > 0 || loading}
          className="zfl-button-secondary zfl-text-sm font-medium"
        >
          {resendCooldown > 0
            ? `Resend code in ${resendCooldown}s`
            : 'Resend code'}
        </button>

        {loading && (
          <p className="zfl-text-sm zfl-text-secondary font-medium">
            Verifying...
          </p>
        )}
      </div>
    </div>
  );
};
