<template>
  <div>
    <div
      v-if="isMobile"
      class="nav-item b-nav-dropdown dropdown"
    >
      <a
        :href="$url('profile', userId)"
        role="menuitem"
        class="nav-link"
      >
        <i class="fas fa-address-card" /> Profil
      </a>
      <a
        :href="$url('settings')"
        role="menuitem"
        class="nav-link"
      >
        <i class="fas fa-cog" /> Einstellungen
      </a>
      <a
        :href="$url('logout')"
        role="menuitem"
        class="nav-link"
      >
        <i class="fas fa-power-off" /> Logout
      </a>
    </div>
    <nav-item-dropdown
      v-else
      no-caret
      class="user"
      right
    >
      <template slot="button-content">
        <img :src="avatar">
      </template>
      <a
        :href="$url('profile', userId)"
        role="menuitem"
        class="dropdown-item"
      >
        <i class="fas fa-address-card" /> Profil
      </a>
      <a
        :href="$url('settings')"
        role="menuitem"
        class="dropdown-item"
      >
        <i class="fas fa-cog" /> Einstellungen
      </a>
      <a
        :href="$url('logout')"
        role="menuitem"
        class="dropdown-item"
      >
        <i class="fas fa-power-off" /> Logout
      </a>
    </nav-item-dropdown>
  </div>
</template>
<script>
import NavItemDropdown from './NavItemDropdown'
export default {
  components: {
    NavItemDropdown
  },
  props: {
    userId: {
      type: Number,
      default: null
    },
    avatar: {
      type: String,
      default: null
    },
    isMobile: {
      type: Boolean,
      default: false
    }
  }
}
</script>

<style lang="scss" scoped>
img {
    height: 1.2rem;
}
</style>
