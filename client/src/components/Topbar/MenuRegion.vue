<template>
  <b-nav-item-dropdown
    ref="dropdown"
    class="regionMenu"
  >
    <template slot="button-content">
      <span class="regionName text-truncate">
        {{ activeRegion ? activeRegion.name : 'Bezirke' }}
      </span>
    </template>
    <div
      v-for="region in regionsSorted"
      :key="region.id"
    >
      <a
        v-if="region.id !== activeRegionId || regions.length !== 1"
        v-b-toggle="`topbarregion_${region.id}`"
        role="menuitem"
        href="#"
        target="_self"
        class="dropdown-item text-truncate"
      >
        {{ region.name }}
      </a>
      <b-collapse
        :id="`topbarregion_${region.id}`"
        :visible="region.id === activeRegionId"
        class="sub"
        accordion="regions"
      >
        <a
          :href="$url('forum', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="far fa-comment" />Forum
        </a>
        <a
          v-if="region.isBot"
          :href="$url('forum', region.id, 1)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="far fa-comment-dots" />Bot-Forum
        </a>
        <a
          :href="$url('fairteiler', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-recycle" />Fair-Teiler
        </a>
        <a
          :href="$url('members', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-user" />Mitglieder
        </a>
        <a
          :href="$url('events', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="far fa-calendar-alt" />Termine
        </a>
        <a
          :href="$url('stores', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-cart-plus" />Betriebe
        </a>
        <a
          :href="$url('workingGroups', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-users" />Arbeitsgruppen
        </a>
        <a
          :href="$url('statistic', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-chart-bar" />Statistik
        </a>
        <a
          v-if="region.isBot"
          :href="$url('foodsaverList', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-user" />Foodsaver
        </a>
        <a
          v-if="region.isBot"
          :href="$url('passports', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-address-card" />Ausweise
        </a>
        <a
          v-if="region.isBot"
          :href="$url('reports', region.id)"
          role="menuitem"
          class="dropdown-item dropdown-item-sub"
        >
          <i class="fas fa-poo" />{{ $i18n('terminology.reports') }}
        </a>
      </b-collapse>
    </div>
    <div
      v-if="regionsSorted.length"
      class="dropdown-divider"
    />
    <a
      @click="joinRegionDialog"
      href="#"
      role="menuitem"
      class="dropdown-item"
    >
      <small><i class="fas fa-plus" /> Einem Bezirk beitreten</small>
    </a>
  </b-nav-item-dropdown>
</template>
<script>
import { BCollapse, BNavItemDropdown, VBToggle } from 'bootstrap-vue'

import { becomeBezirk } from '@/script'

export default {
  components: { BCollapse, BNavItemDropdown },
  directives: { VBToggle },
  props: {
    regions: {
      type: Array,
      default: () => []
    },
    activeRegionId: {
      type: Number,
      default: null
    }
  },
  computed: {
    activeRegion () {
      return this.regions.find(r => r.id === this.activeRegionId)
    },
    regionsSorted () {
      return this.regions.slice().sort((a, b) => {
        if (this.activeRegionId && a.id === this.activeRegionId) return -1
        if (this.activeRegionId && b.id === this.activeRegionId) return 1
        else return a.name.localeCompare(b.name)
      })
    }
  },
  methods: {
    joinRegionDialog () {
      this.$refs.dropdown.visible = false
      becomeBezirk()
    }
  }
}
</script>

<style lang="scss">
.regionMenu {
    margin-top: 0.1em;

    @media (max-width: 350px) {
        .dropdown-toggle::after {
            content: none;
        }
    }
}
.regionMenu > a.nav-link {
    font-family: 'Alfa Slab One',serif;
    font-size: 1em !important;
}
</style>
<style lang="scss" scoped>
.regionName {
    max-width: 120px;
    display: inline-block;
    margin-bottom: -0.35em;
}
</style>
