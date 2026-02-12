<div
    x-data="{
        token: @js($token),
        copied: false,
        copy() {
            navigator.clipboard.writeText(this.token)
            this.copied = true
            setTimeout(() => this.copied = false, 2000)
        }
    }"
    style="display: flex; flex-direction: column; gap: 16px;"
>
    <p style="font-size: 14px; color: #6b7280; margin: 0;">
        This token will only be shown once. Please copy it now.
    </p>

    <div style="
        padding: 16px;
        background-color: #f3f4f6;
        border-radius: 8px;
        font-family: monospace;
        font-size: 13px;
        word-break: break-all;
        border: 1px solid #e5e7eb;
    ">
        {{ $token }}
    </div>

    <button
        type="button"
        @click="copy()"
        class="fi-color fi-color-primary fi-bg-color-400 hover:fi-bg-color-300
               dark:fi-bg-color-600 dark:hover:fi-bg-color-500
               fi-text-color-900 hover:fi-text-color-800
               dark:fi-text-color-950 dark:hover:fi-text-color-950
               fi-btn fi-size-md fi-ac-btn-action"
    >
        <span x-show="!copied">Copy Token</span>
        <span x-show="copied">Copied ✓</span>
    </button>
</div>
