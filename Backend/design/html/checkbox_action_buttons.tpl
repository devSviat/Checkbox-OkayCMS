<span class="sviat__checkbox_buttons_block">
    {if $checkboxActiveShift}
        {if $checkboxActiveShift->status == 'OPENED'}
            <button type="button" class="btn_admin sviat__checkbox_status_button sviat__checkbox_status_button--opened fn-sviat-checkbox-action-shift hint-bottom-middle-t-info-s-small-mobile hint-anim" data-href="{url_generator route="Sviat_Checkbox_closeShift" absolute=1}" data-hint="{$btr->sviat__checkbox__opened_shift|escape} {$checkboxActiveShift->opened_at|date_format:"H:i d.m.Y"}">
                <span class="sviat__checkbox_status_badge sviat__checkbox_status_badge--green"></span>
                <span class="sviat__checkbox_status_logo">
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g clip-path="url(#checkbox_logo)">
                            <path d="M11.4256 22C14.0781 22 16.2768 21.3065 18.0216 19.89C19.7664 18.4735 20.8586 16.8504 21.3125 14.9912L16.6031 13.7518C16.3194 14.7258 15.7095 15.5519 14.7733 16.2455C13.837 16.939 12.7022 17.2783 11.3688 17.2783C9.66657 17.2783 8.27648 16.6586 7.21264 15.4191C6.14879 14.1797 5.62385 12.7043 5.62385 10.9631C5.62385 9.22204 6.14879 7.73166 7.1984 6.52185C8.24816 5.3119 9.63824 4.70692 11.3688 4.70692C12.688 4.70692 13.8229 5.07574 14.7733 5.78406C15.7095 6.50705 16.3194 7.34803 16.6031 8.30717L21.3125 7.09721C20.8586 5.23816 19.7664 3.5855 18.0074 2.1542C16.2485 0.722997 14.0355 0 11.3405 0C8.30481 0 5.76576 1.06237 3.73728 3.15761C1.69464 5.26763 0.6875 7.87927 0.6875 10.9926C0.6875 14.1503 1.69464 16.7619 3.73728 18.8571C5.77985 20.9523 8.33328 22 11.4256 22Z" fill="url(#checkbox_linear)"/>
                        </g>
                        <defs>
                            <linearGradient id="checkbox_linear" x1="2.24217" y1="21.5721" x2="33.1923" y2="-8.18197" gradientUnits="userSpaceOnUse">
                                <stop stop-color="white"/>
                                <stop offset="1" stop-color="white" stop-opacity="0"/>
                            </linearGradient>
                            <clipPath id="checkbox_logo">
                                <rect width="22" height="22" fill="white"/>
                            </clipPath>
                        </defs>
                    </svg>
                </span>
                <span class="sviat__checkbox_status_loader hidden">
                    <span class="spinner"></span>
                </span>
            </button>
        {elseif $checkboxActiveShift->status == 'CREATED'}
            <button type="button" class="btn_admin sviat__checkbox_status_button sviat__checkbox_status_button--created hint-bottom-middle-t-info-s-small-mobile hint-anim " disabled data-hint="{$btr->sviat__checkbox__just_created_shift|escape}">
                <span class="sviat__checkbox_status_badge sviat__checkbox_status_badge--yellow"></span>
                <span class="sviat__checkbox_status_logo">
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g clip-path="url(#checkbox_logo)">
                            <path d="M11.4256 22C14.0781 22 16.2768 21.3065 18.0216 19.89C19.7664 18.4735 20.8586 16.8504 21.3125 14.9912L16.6031 13.7518C16.3194 14.7258 15.7095 15.5519 14.7733 16.2455C13.837 16.939 12.7022 17.2783 11.3688 17.2783C9.66657 17.2783 8.27648 16.6586 7.21264 15.4191C6.14879 14.1797 5.62385 12.7043 5.62385 10.9631C5.62385 9.22204 6.14879 7.73166 7.1984 6.52185C8.24816 5.3119 9.63824 4.70692 11.3688 4.70692C12.688 4.70692 13.8229 5.07574 14.7733 5.78406C15.7095 6.50705 16.3194 7.34803 16.6031 8.30717L21.3125 7.09721C20.8586 5.23816 19.7664 3.5855 18.0074 2.1542C16.2485 0.722997 14.0355 0 11.3405 0C8.30481 0 5.76576 1.06237 3.73728 3.15761C1.69464 5.26763 0.6875 7.87927 0.6875 10.9926C0.6875 14.1503 1.69464 16.7619 3.73728 18.8571C5.77985 20.9523 8.33328 22 11.4256 22Z" fill="url(#checkbox_linear)"/>
                        </g>
                        <defs>
                            <linearGradient id="checkbox_linear" x1="2.24217" y1="21.5721" x2="33.1923" y2="-8.18197" gradientUnits="userSpaceOnUse">
                                <stop stop-color="white"/>
                                <stop offset="1" stop-color="white" stop-opacity="0"/>
                            </linearGradient>
                            <clipPath id="checkbox_logo">
                                <rect width="22" height="22" fill="white"/>
                            </clipPath>
                        </defs>
                    </svg>
                </span>
            </button>
        {else}
            <button type="button" class="btn_admin sviat__checkbox_status_button sviat__checkbox_status_button--closed hint-bottom-middle-t-info-s-small-mobile hint-anim " disabled data-hint="{$btr->sviat__checkbox__close_shift|escape}{if $checkboxActiveShift->closed_at} {$checkboxActiveShift->closed_at|date_format:"H:i d.m.Y"}{/if}">
                <span class="sviat__checkbox_status_badge sviat__checkbox_status_badge--red"></span>
                <span class="sviat__checkbox_status_logo">
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g clip-path="url(#checkbox_logo)">
                            <path d="M11.4256 22C14.0781 22 16.2768 21.3065 18.0216 19.89C19.7664 18.4735 20.8586 16.8504 21.3125 14.9912L16.6031 13.7518C16.3194 14.7258 15.7095 15.5519 14.7733 16.2455C13.837 16.939 12.7022 17.2783 11.3688 17.2783C9.66657 17.2783 8.27648 16.6586 7.21264 15.4191C6.14879 14.1797 5.62385 12.7043 5.62385 10.9631C5.62385 9.22204 6.14879 7.73166 7.1984 6.52185C8.24816 5.3119 9.63824 4.70692 11.3688 4.70692C12.688 4.70692 13.8229 5.07574 14.7733 5.78406C15.7095 6.50705 16.3194 7.34803 16.6031 8.30717L21.3125 7.09721C20.8586 5.23816 19.7664 3.5855 18.0074 2.1542C16.2485 0.722997 14.0355 0 11.3405 0C8.30481 0 5.76576 1.06237 3.73728 3.15761C1.69464 5.26763 0.6875 7.87927 0.6875 10.9926C0.6875 14.1503 1.69464 16.7619 3.73728 18.8571C5.77985 20.9523 8.33328 22 11.4256 22Z" fill="url(#checkbox_linear)"/>
                        </g>
                        <defs>
                            <linearGradient id="checkbox_linear" x1="2.24217" y1="21.5721" x2="33.1923" y2="-8.18197" gradientUnits="userSpaceOnUse">
                                <stop stop-color="white"/>
                                <stop offset="1" stop-color="white" stop-opacity="0"/>
                            </linearGradient>
                            <clipPath id="checkbox_logo">
                                <rect width="22" height="22" fill="white"/>
                            </clipPath>
                        </defs>
                    </svg>
                </span>
            </button>
        {/if}
    {else}
        <button type="button" class="btn_admin sviat__checkbox_status_button sviat__checkbox_status_button--closed fn-sviat-checkbox-action-shift hint-bottom-middle-t-info-s-small-mobile hint-anim " data-href="{url_generator route="Sviat_Checkbox_createShift" absolute=1}" data-hint="{$btr->sviat__checkbox__create_shift|escape}">
            <span class="sviat__checkbox_status_badge sviat__checkbox_status_badge--red"></span>
            <span class="sviat__checkbox_status_logo">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#checkbox_logo)">
                        <path d="M11.4256 22C14.0781 22 16.2768 21.3065 18.0216 19.89C19.7664 18.4735 20.8586 16.8504 21.3125 14.9912L16.6031 13.7518C16.3194 14.7258 15.7095 15.5519 14.7733 16.2455C13.837 16.939 12.7022 17.2783 11.3688 17.2783C9.66657 17.2783 8.27648 16.6586 7.21264 15.4191C6.14879 14.1797 5.62385 12.7043 5.62385 10.9631C5.62385 9.22204 6.14879 7.73166 7.1984 6.52185C8.24816 5.3119 9.63824 4.70692 11.3688 4.70692C12.688 4.70692 13.8229 5.07574 14.7733 5.78406C15.7095 6.50705 16.3194 7.34803 16.6031 8.30717L21.3125 7.09721C20.8586 5.23816 19.7664 3.5855 18.0074 2.1542C16.2485 0.722997 14.0355 0 11.3405 0C8.30481 0 5.76576 1.06237 3.73728 3.15761C1.69464 5.26763 0.6875 7.87927 0.6875 10.9926C0.6875 14.1503 1.69464 16.7619 3.73728 18.8571C5.77985 20.9523 8.33328 22 11.4256 22Z" fill="url(#checkbox_linear)"/>
                    </g>
                    <defs>
                        <linearGradient id="checkbox_linear" x1="2.24217" y1="21.5721" x2="33.1923" y2="-8.18197" gradientUnits="userSpaceOnUse">
                            <stop stop-color="white"/>
                            <stop offset="1" stop-color="white" stop-opacity="0"/>
                        </linearGradient>
                        <clipPath id="checkbox_logo">
                            <rect width="22" height="22" fill="white"/>
                        </clipPath>
                    </defs>
                </svg>
            </span>
            <span class="sviat__checkbox_status_loader hidden">
                <span class="spinner"></span>
            </span>
        </button>
    {/if}
</span>

