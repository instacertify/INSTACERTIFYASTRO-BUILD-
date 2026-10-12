<style>
    /* Light-mode only: soft primary tints so the active menu item, section headers, table rows and tabs stand apart. */
    html:not(.dark) {
        --ic-tint: color-mix(in oklab, var(--primary-50) 75%, white);
        --ic-tint-strong: var(--primary-100);
    }

    html:not(.dark) .fi-sidebar-group-label {
        color: var(--primary-700);
        font-weight: 600;
        letter-spacing: .02em;
    }

    html:not(.dark) .fi-sidebar-item-btn:hover {
        background-color: var(--ic-tint);
    }

    html:not(.dark) .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--ic-tint-strong);
        box-shadow: inset 3px 0 0 var(--primary-600);
    }

    html:not(.dark) .fi-sidebar-item.fi-active > .fi-sidebar-item-btn :is(.fi-sidebar-item-label, .fi-icon) {
        color: var(--primary-800);
    }

    html:not(.dark) .fi-section:not(.fi-section-not-contained) > .fi-section-header {
        background-color: var(--ic-tint);
        border-bottom: 1px solid var(--primary-100);
        border-top-left-radius: inherit;
        border-top-right-radius: inherit;
    }

    html:not(.dark) .fi-ta-header-cell {
        background-color: var(--ic-tint);
    }

    html:not(.dark) .fi-ta-row:nth-child(even) {
        background-color: color-mix(in oklab, var(--gray-50) 70%, white);
    }

    html:not(.dark) .fi-ta-row:hover {
        background-color: var(--ic-tint);
    }

    html:not(.dark) .fi-tabs-item.fi-active {
        background-color: var(--ic-tint-strong);
    }

    html:not(.dark) .fi-fo-repeater-item-header {
        background-color: var(--ic-tint);
        border-top-left-radius: inherit;
        border-top-right-radius: inherit;
    }

    html:not(.dark) .fi-topbar {
        box-shadow: 0 1px 0 var(--primary-100);
    }
</style>
