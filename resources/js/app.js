document.addEventListener('alpine:init', () => {
    window.Alpine.data('filterBadges', (moreTemplate) => ({
        hiddenKeys: [],
        total: 0,
        widths: [],

        init() {
            this.resizeObserver = new ResizeObserver(() => this.layout());
            this.resizeObserver.observe(this.$el);

            this.mutationObserver = new MutationObserver(() => this.measure());
            this.mutationObserver.observe(this.$refs.list, { childList: true });

            this.measure();
        },

        destroy() {
            this.resizeObserver?.disconnect();
            this.mutationObserver?.disconnect();
        },

        moreLabel(count) {
            return moreTemplate.replace(':count', count);
        },

        badges() {
            return [...this.$refs.list.children];
        },

        measure() {
            this.hiddenKeys = [];

            this.$nextTick(() => {
                const badges = this.badges();

                this.total = badges.length;
                this.widths = badges.map((badge) => badge.getBoundingClientRect().width);

                this.$nextTick(() => this.layout());
            });
        },

        layout() {
            const badges = this.badges();

            if (badges.length !== this.widths.length) {
                return;
            }

            const style = getComputedStyle(this.$el);
            const gap = parseFloat(style.columnGap) || 0;
            const available = this.$el.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const clearWidth = this.$refs.clear.getBoundingClientRect().width + gap;
            const moreWidth = this.$refs.moreProbe.getBoundingClientRect().width + gap;
            const totalWidth = this.widths.reduce((sum, width) => sum + width, 0) + gap * Math.max(0, this.widths.length - 1);

            if (totalWidth <= available - clearWidth) {
                this.hiddenKeys = [];

                return;
            }

            const budget = available - clearWidth - moreWidth;
            let used = 0;
            let visible = 0;

            for (const width of this.widths) {
                const next = used + (visible > 0 ? gap : 0) + width;

                if (next > budget) {
                    break;
                }

                used = next;
                visible++;
            }

            this.hiddenKeys = badges.slice(visible).map((badge) => badge.dataset.key);
        },
    }));
});
