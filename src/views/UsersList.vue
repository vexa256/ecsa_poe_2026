<template>
  <IonPage>
    <!-- ═══════════════════════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════════════════════════ -->
    <IonHeader class="ion-no-border s-header">
      <IonToolbar class="s-toolbar">
        <!-- top row -->
        <div class="hdr-top">
          <div class="hdr-left">
            <button class="back-btn" @click="goBack">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="title-block">
              <span class="eyebrow">ECSA·HC &nbsp;·&nbsp; Sentinel POE</span>
              <div class="page-title">Manage Users &amp; Accounts</div>
            </div>
          </div>
          <div class="hdr-right">
            <!-- sync button -->
            <button class="hbtn" :class="{ 'hbtn-warn': pendingCount > 0 }" @click="syncAll">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
                <path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
              </svg>
              <div v-if="pendingCount > 0" class="badge">{{ pendingCount > 9 ? '9+' : pendingCount }}</div>
            </button>
            <!-- add user -->
            <button class="hbtn hbtn-blue" @click="openCreateModal">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- sync status strip -->
        <div class="strip" :class="stripClass">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="strip-icon">
            <polyline v-if="stripMode==='ok'" points="20 6 9 17 4 12"/>
            <template v-else-if="stripMode==='sync'">
              <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
            </template>
            <template v-else>
              <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
              <path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
            </template>
          </svg>
          <span class="strip-txt">{{ stripText }}</span>
        </div>

        <!-- stats row -->
        <div class="stats-row">
          <div class="stat-cell">
            <span class="stat-n n-total">{{ allUsers.length }}</span>
            <span class="stat-l">Total</span>
          </div>
          <div class="stat-cell">
            <span class="stat-n n-ok">{{ syncedCount }}</span>
            <span class="stat-l">Uploaded</span>
          </div>
          <div class="stat-cell">
            <span class="stat-n n-warn">{{ pendingCount }}</span>
            <span class="stat-l">Pending</span>
          </div>
          <div class="stat-cell">
            <span class="stat-n n-fail">{{ failedCount }}</span>
            <span class="stat-l">Queued</span>
          </div>
        </div>

        <!-- search -->
        <div class="search-area">
          <div class="search-wrap">
            <svg class="search-ic" viewBox="0 0 24 24" fill="none" stroke="#4A6FA5" stroke-width="2" stroke-linecap="round">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
              v-model="searchQ"
              class="search-input"
              placeholder="Search name, username, role, POE…"
              @input="onSearchInput"
            />
            <button v-if="searchQ" class="search-clear" @click="searchQ='';loadUsers()">
              <svg viewBox="0 0 24 24" fill="none" stroke="#4A6FA5" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>

        <!-- role chips -->
        <div class="chips-row">
          <button
            v-for="chip in roleChips"
            :key="chip.value"
            class="chip"
            :class="[chip.cls, { 'chip-active': activeRole === chip.value }]"
            @click="setRole(chip.value)"
          >{{ chip.label }}</button>
        </div>

        <!-- active/inactive toggle -->
        <div class="status-toggle-row">
          <button
            v-for="opt in statusOpts"
            :key="opt.value"
            class="stog-btn"
            :class="{ 'stog-active': activeStatus === opt.value }"
            @click="setStatus(opt.value)"
          >
            <span class="stog-dot" :class="opt.dotCls"></span>
            {{ opt.label }}
          </button>
          <!-- poe filter: only show poes from server returned data -->
          <select v-if="serverPoes.length" v-model="activePoe" class="poe-select" @change="loadUsers">
            <option value="">All POEs</option>
            <option v-for="p in serverPoes" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
      </IonToolbar>
    </IonHeader>

    <!-- ═══════════════════════════════════════════════════════════════
         CONTENT
    ════════════════════════════════════════════════════════════════ -->
    <IonContent class="s-content" :scroll-events="true">
      <IonRefresher slot="fixed" @ionRefresh="onRefresh">
        <IonRefresherContent :pulling-icon="chevronDownCircleOutline" refreshing-spinner="crescent"/>
      </IonRefresher>

      <div class="content-wrap">
        <!-- poe scope banner -->
        <div v-if="auth && auth.poe_code" class="poe-banner">
          <svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
          <span class="poe-banner-txt">
            Showing users at
            <strong>{{ activePoe || auth.poe_code }}</strong>
            <template v-if="auth.district_code"> · {{ auth.district_code }}</template>
          </span>
        </div>

        <!-- section header -->
        <div class="sec-hdr">
          <span class="sec-lbl">Officers</span>
          <span class="sec-count">{{ filteredUsers.length }} user{{ filteredUsers.length !== 1 ? 's' : '' }}</span>
        </div>

        <!-- loading skeleton -->
        <template v-if="loading">
          <div v-for="i in 4" :key="i" class="skel-card">
            <div class="skel-accent"></div>
            <div class="skel-body">
              <div class="skel-avatar"></div>
              <div class="skel-lines">
                <div class="skel-line" style="width:60%"></div>
                <div class="skel-line" style="width:40%;height:10px;margin-top:6px"></div>
                <div style="display:flex;gap:6px;margin-top:10px">
                  <div class="skel-line" style="width:70px;height:20px;border-radius:7px"></div>
                  <div class="skel-line" style="width:60px;height:20px;border-radius:7px"></div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- empty state -->
        <div v-else-if="!loading && filteredUsers.length === 0" class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
          </div>
          <p class="empty-title">No users found</p>
          <p class="empty-sub">{{ searchQ ? 'Try a different search term' : 'Add the first user for this POE' }}</p>
          <button class="empty-btn" @click="openCreateModal">Add User</button>
        </div>

        <!-- user cards -->
        <template v-else>
          <div
            v-for="user in filteredUsers"
            :key="user.client_uuid"
            class="ucard"
            :class="{ 'ucard-inactive': !user.is_active, 'ucard-expanded': expandedId === user.client_uuid }"
            :style="{ borderColor: roleColor(user.role_key, 0.18) }"
          >
            <div class="ucard-accent" :style="{ background: roleAccent(user.role_key) }"></div>
            <div class="ucard-body" @click="toggleExpand(user)">
              <div class="avatar" :style="avatarStyle(user.role_key)">
                {{ initials(user.full_name || user.name) }}
                <div class="av-dot" :class="user.is_active ? 'dot-active' : 'dot-inactive'"></div>
              </div>
              <div class="ucard-info">
                <div class="ucard-name">{{ user.full_name || user.name || '—' }}</div>
                <div class="ucard-username">
                  @{{ user.username }}&nbsp;·&nbsp;ID: {{ user.id || '—' }}
                  <span v-if="!user.is_active" class="inactive-label">INACTIVE</span>
                </div>
                <div class="ucard-tags">
                  <span class="role-badge" :style="roleBadgeStyle(user.role_key)">{{ roleLabel(user.role_key) }}</span>
                  <span v-if="user.poe_code || user.assignment?.poe_code" class="poe-tag">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    {{ user.poe_code || user.assignment?.poe_code }}
                  </span>
                </div>
              </div>
              <div class="ucard-right">
                <span class="sync-pill" :class="syncPillCls(user.sync_status)">
                  <div class="sync-dot" :class="syncDotCls(user.sync_status)"></div>
                  {{ syncLabel(user.sync_status) }}
                </span>
                <button class="ucard-action" @click.stop="openActionSheet(user)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round">
                    <circle cx="12" cy="5" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="12" cy="19" r="1.2"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- expanded details -->
            <transition name="expand">
              <div v-if="expandedId === user.client_uuid" class="ucard-details">
                <div class="det-section-title">Identity</div>
                <div class="det-row">
                  <span class="det-k"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Server ID</span>
                  <span class="det-v det-mono">{{ user.id || 'Not synced' }}</span>
                </div>
                <div class="det-row">
                  <span class="det-k"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 01-2.18 2A19.79 19.79 0 019.69 17.31a19.5 19.5 0 01-6-6 19.79 19.79 0 01-4.6-9.9A2 2 0 011.07 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L5.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>Phone</span>
                  <span class="det-v">{{ user.phone || '—' }}</span>
                </div>
                <div class="det-row">
                  <span class="det-k"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Email</span>
                  <span class="det-v det-small">{{ user.email || '—' }}</span>
                </div>
                <div class="det-section-title" style="margin-top:8px">Assignment</div>
                <div class="det-row">
                  <span class="det-k"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>PHEOC</span>
                  <span class="det-v">{{ user.assignment?.pheoc_code || user.pheoc_code || '—' }}</span>
                </div>
                <div class="det-row">
                  <span class="det-k"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>District</span>
                  <span class="det-v">{{ user.assignment?.district_code || user.district_code || '—' }}</span>
                </div>
                <div class="det-row">
                  <span class="det-k"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POE</span>
                  <span class="det-v">{{ user.assignment?.poe_code || user.poe_code || '—' }}</span>
                </div>
                <div class="det-section-title" style="margin-top:8px">Audit</div>
                <div class="det-row">
                  <span class="det-k">Created</span>
                  <span class="det-v det-small">{{ fmtDate(user.created_at) }}</span>
                </div>
                <div class="det-row">
                  <span class="det-k">Last Login</span>
                  <span class="det-v det-small">{{ fmtDate(user.last_login_at) || 'Never' }}</span>
                </div>
                <div class="det-row">
                  <span class="det-k">Sync Status</span>
                  <span class="det-v">
                    <span class="sync-pill" :class="syncPillCls(user.sync_status)" style="font-size:10px">
                      <div class="sync-dot" :class="syncDotCls(user.sync_status)"></div>
                      {{ syncLabel(user.sync_status) }}
                    </span>
                  </span>
                </div>
                <!-- quick actions inline -->
                <div class="inline-actions">
                  <button class="ia-btn ia-edit" @click.stop="openEditModal(user)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                  </button>
                  <button class="ia-btn" :class="user.is_active ? 'ia-deact' : 'ia-act'" @click.stop="toggleStatus(user)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                      <path v-if="user.is_active" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                      <path v-else d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ user.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                  <button v-if="user.sync_status !== 'SYNCED'" class="ia-btn ia-sync" @click.stop="syncOne(user)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                      <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                    </svg>
                    Upload
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </template>

        <div style="height:100px"></div>
      </div>
    </IonContent>

    <!-- FAB -->
    <div class="fab-container">
      <button class="fab" @click="openCreateModal">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         CREATE / EDIT MODAL (full-screen)
    ════════════════════════════════════════════════════════════════ -->
    <IonModal
      :is-open="showFormModal"
      :breakpoints="[0, 1]"
      :initial-breakpoint="1"
      @didDismiss="closeFormModal"
      class="s-modal"
    >
      <IonPage>
        <IonHeader class="ion-no-border s-modal-header">
          <IonToolbar class="s-modal-toolbar">
            <div class="modal-hdr">
              <div>
                <span class="eyebrow">{{ editingUser ? 'EDIT USER' : 'NEW USER' }}</span>
                <div class="modal-title">{{ editingUser ? 'Update Profile' : 'Add Officer' }}</div>
              </div>
              <button class="modal-close" @click="closeFormModal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </IonToolbar>
        </IonHeader>
        <IonContent class="s-modal-content">
          <div class="form-wrap">

            <!-- IDENTITY SECTION -->
            <div class="form-section-title">Identity</div>

            <div class="form-group" :class="{ 'form-error': errors.full_name }">
              <label class="form-label">Full Name <span class="req">*</span></label>
              <input v-model="form.full_name" class="form-input" placeholder="e.g. AYEBARE TIMOTHY KAMUKAMA" maxlength="150"/>
              <span v-if="errors.full_name" class="form-err-msg">{{ errors.full_name }}</span>
            </div>

            <div class="form-group" :class="{ 'form-error': errors.username }">
              <label class="form-label">Username <span class="req">*</span></label>
              <input v-model="form.username" class="form-input" placeholder="e.g. ayebare.t" maxlength="80" autocomplete="off"/>
              <span v-if="errors.username" class="form-err-msg">{{ errors.username }}</span>
            </div>

            <div class="form-group" :class="{ 'form-error': errors.email }">
              <label class="form-label">Email</label>
              <input v-model="form.email" type="email" class="form-input" placeholder="officer@ecsa.org" maxlength="190"/>
              <span v-if="errors.email" class="form-err-msg">{{ errors.email }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Phone</label>
              <input v-model="form.phone" class="form-input" placeholder="25678927376 (no +)" maxlength="40"/>
            </div>

            <div class="form-group" :class="{ 'form-error': errors.password }">
              <label class="form-label">Password {{ editingUser ? '(leave blank to keep current)' : '' }} <span v-if="!editingUser" class="req">*</span></label>
              <div class="pw-wrap">
                <input
                  v-model="form.password"
                  :type="showPw ? 'text' : 'password'"
                  class="form-input pw-input"
                  placeholder="Min 8 characters"
                  autocomplete="new-password"
                />
                <button class="pw-toggle" @click="showPw = !showPw">
                  <svg v-if="!showPw" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <span v-if="errors.password" class="form-err-msg">{{ errors.password }}</span>
            </div>

            <!-- ROLE -->
            <div class="form-section-title" style="margin-top:18px">Role & Status</div>

            <div class="form-group" :class="{ 'form-error': errors.role_key }">
              <label class="form-label">Role <span class="req">*</span></label>
              <div class="role-grid">
                <button
                  v-for="r in ROLES"
                  :key="r.value"
                  class="role-opt"
                  :class="{ 'role-opt-active': form.role_key === r.value }"
                  :style="form.role_key === r.value ? { background: roleAccent(r.value) + '18', borderColor: roleAccent(r.value), color: roleAccent(r.value) } : {}"
                  @click="form.role_key = r.value; form.assignment.poe_code = ''"
                >
                  {{ r.label }}
                </button>
              </div>
              <span v-if="errors.role_key" class="form-err-msg">{{ errors.role_key }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Status</label>
              <div class="toggle-row">
                <button class="tog-btn" :class="{ 'tog-active-on': form.is_active }" @click="form.is_active = true">
                  <span class="stog-dot dot-active"></span> Active
                </button>
                <button class="tog-btn" :class="{ 'tog-active-off': !form.is_active }" @click="form.is_active = false">
                  <span class="stog-dot dot-inactive"></span> Inactive
                </button>
              </div>
            </div>

            <!-- GEOGRAPHY SECTION -->
            <div class="form-section-title" style="margin-top:18px">Geographic Assignment</div>
            <div class="geo-hint">
              <svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Each field is filtered by the previous selection using live server data.
            </div>

            <!-- Country (fixed) -->
            <div class="form-group">
              <label class="form-label">Country</label>
              <input class="form-input form-input-readonly" value="Uganda (UG)" readonly/>
            </div>

            <!-- PHEOC / Province -->
            <div v-if="geoRequired('province_or_pheoc')" class="form-group" :class="{ 'form-error': errors['assignment.province_code'] }">
              <label class="form-label">PHEOC / Province <span class="req">*</span></label>
              <select v-model="form.assignment.province_code" class="form-select" @change="onPheocChange">
                <option value="">— Select PHEOC —</option>
                <!-- inject current value if not in list (edit mode) -->
                <option v-if="editingUser && form.assignment.province_code && !PHEOC_LIST.includes(form.assignment.province_code)" :value="form.assignment.province_code">{{ form.assignment.province_code }}</option>
                <option v-for="p in PHEOC_LIST" :key="p" :value="p">{{ p }}</option>
              </select>
              <span v-if="errors['assignment.province_code']" class="form-err-msg">{{ errors['assignment.province_code'] }}</span>
            </div>

            <!-- District -->
            <div v-if="geoRequired('district_code')" class="form-group" :class="{ 'form-error': errors['assignment.district_code'] }">
              <label class="form-label">District <span class="req">*</span></label>
              <select v-model="form.assignment.district_code" class="form-select" @change="onDistrictChange">
                <option value="">— Select District —</option>
                <option v-for="d in filteredDistricts" :key="d" :value="d">{{ d }}</option>
              </select>
              <span v-if="errors['assignment.district_code']" class="form-err-msg">{{ errors['assignment.district_code'] }}</span>
            </div>

            <!-- POE — ONLY poes returned by server from the user list -->
            <div v-if="geoRequired('poe_code')" class="form-group" :class="{ 'form-error': errors['assignment.poe_code'] }">
              <label class="form-label">Point of Entry <span class="req">*</span></label>
              <select v-model="form.assignment.poe_code" class="form-select">
                <option value="">— Select POE —</option>
                <option v-for="p in filteredPoes" :key="p" :value="p">{{ p }}</option>
              </select>
              <span v-if="errors['assignment.poe_code']" class="form-err-msg">{{ errors['assignment.poe_code'] }}</span>
            </div>

            <!-- global form error -->
            <div v-if="formError" class="form-alert-error">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              {{ formError }}
            </div>

            <!-- form success -->
            <div v-if="formSuccess" class="form-alert-ok">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              {{ formSuccess }}
            </div>

            <!-- submit -->
            <button class="submit-btn" :disabled="formSubmitting" @click="submitForm">
              <span v-if="formSubmitting" class="spinner"></span>
              <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <template v-if="editingUser"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></template>
                <template v-else><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></template>
              </svg>
              {{ editingUser ? 'Save Changes' : 'Create User' }}
            </button>
            <div style="height:40px"></div>
          </div>
        </IonContent>
      </IonPage>
    </IonModal>

    <!-- ═══════════════════════════════════════════════════════════════
         USER DETAILS MODAL (full-screen)
    ════════════════════════════════════════════════════════════════ -->
    <IonModal
      :is-open="showDetailModal"
      :breakpoints="[0, 1]"
      :initial-breakpoint="1"
      @didDismiss="closeDetailModal"
      class="s-modal"
    >
      <IonPage v-if="detailUser">
        <IonHeader class="ion-no-border s-modal-header">
          <IonToolbar class="s-modal-toolbar">
            <div class="modal-hdr">
              <div>
                <span class="eyebrow">USER PROFILE</span>
                <div class="modal-title">{{ detailUser.full_name || detailUser.name }}</div>
              </div>
              <button class="modal-close" @click="closeDetailModal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </IonToolbar>
        </IonHeader>
        <IonContent class="s-modal-content">
          <div class="detail-wrap">
            <!-- hero card -->
            <div class="detail-hero" :style="{ borderTopColor: roleAccent(detailUser.role_key) }">
              <div class="detail-avatar" :style="avatarStyle(detailUser.role_key, true)">
                {{ initials(detailUser.full_name || detailUser.name) }}
              </div>
              <div class="detail-hero-info">
                <div class="detail-hero-name">{{ detailUser.full_name || detailUser.name || '—' }}</div>
                <div class="detail-hero-sub">@{{ detailUser.username }} · ID {{ detailUser.id }}</div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap">
                  <span class="role-badge" :style="roleBadgeStyle(detailUser.role_key)">{{ roleLabel(detailUser.role_key) }}</span>
                  <span v-if="!detailUser.is_active" style="font-size:10px;font-weight:800;color:#DC2626;background:#FEF2F2;border:1px solid #FECACA;padding:3px 8px;border-radius:6px;letter-spacing:.5px">INACTIVE</span>
                  <span v-else style="font-size:10px;font-weight:800;color:#065F46;background:#ECFDF5;border:1px solid #A7F3D0;padding:3px 8px;border-radius:6px;letter-spacing:.5px">ACTIVE</span>
                </div>
              </div>
            </div>

            <!-- detail sections -->
            <div class="detail-section">
              <div class="detail-sec-title">Contact Information</div>
              <div class="detail-row"><span class="det-k">Email</span><span class="det-v">{{ detailUser.email || '—' }}</span></div>
              <div class="detail-row"><span class="det-k">Phone</span><span class="det-v">{{ detailUser.phone || '—' }}</span></div>
              <div class="detail-row"><span class="det-k">Country</span><span class="det-v">{{ detailUser.country_code || '—' }}</span></div>
            </div>

            <div class="detail-section">
              <div class="detail-sec-title">Geographic Assignment</div>
              <div class="detail-row"><span class="det-k">PHEOC</span><span class="det-v">{{ detailUser.assignment?.pheoc_code || detailUser.pheoc_code || '—' }}</span></div>
              <div class="detail-row"><span class="det-k">Province</span><span class="det-v">{{ detailUser.assignment?.province_code || detailUser.province_code || '—' }}</span></div>
              <div class="detail-row"><span class="det-k">District</span><span class="det-v">{{ detailUser.assignment?.district_code || detailUser.district_code || '—' }}</span></div>
              <div class="detail-row"><span class="det-k">POE</span><span class="det-v">{{ detailUser.assignment?.poe_code || detailUser.poe_code || '—' }}</span></div>
              <div class="detail-row"><span class="det-k">Ass. Active</span>
                <span class="det-v">
                  <span :style="detailUser.assignment?.is_active ? 'color:#065F46;font-weight:700' : 'color:#DC2626;font-weight:700'">
                    {{ detailUser.assignment?.is_active ? 'Yes' : 'No' }}
                  </span>
                </span>
              </div>
            </div>

            <div class="detail-section">
              <div class="detail-sec-title">System Audit</div>
              <div class="detail-row"><span class="det-k">Server ID</span><span class="det-v det-mono">{{ detailUser.id || 'Pending sync' }}</span></div>
              <div class="detail-row"><span class="det-k">Created</span><span class="det-v">{{ fmtDate(detailUser.created_at) }}</span></div>
              <div class="detail-row"><span class="det-k">Updated</span><span class="det-v">{{ fmtDate(detailUser.updated_at) }}</span></div>
              <div class="detail-row"><span class="det-k">Last Login</span><span class="det-v">{{ fmtDate(detailUser.last_login_at) || 'Never' }}</span></div>
              <div class="detail-row">
                <span class="det-k">Sync</span>
                <span class="sync-pill" :class="syncPillCls(detailUser.sync_status)">
                  <div class="sync-dot" :class="syncDotCls(detailUser.sync_status)"></div>
                  {{ syncLabel(detailUser.sync_status) }}
                </span>
              </div>
            </div>

            <!-- action buttons -->
            <div class="detail-actions">
              <button class="det-act-btn det-act-edit" @click="closeDetailModal(); openEditModal(detailUser)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit User
              </button>
              <button class="det-act-btn" :class="detailUser.is_active ? 'det-act-deact' : 'det-act-act'" @click="toggleStatus(detailUser); closeDetailModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path v-if="detailUser.is_active" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                  <path v-else d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ detailUser.is_active ? 'Deactivate' : 'Activate' }}
              </button>
              <button v-if="detailUser.sync_status !== 'SYNCED'" class="det-act-btn det-act-sync" @click="syncOne(detailUser); closeDetailModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                </svg>
                Upload Now
              </button>
            </div>

            <div style="height:40px"></div>
          </div>
        </IonContent>
      </IonPage>
    </IonModal>

    <!-- ═══════════════════════════════════════════════════════════════
         ACTION SHEET MODAL
    ════════════════════════════════════════════════════════════════ -->
    <IonModal
      :is-open="showActionSheet"
      :breakpoints="[0, 0.45]"
      :initial-breakpoint="0.45"
      @didDismiss="showActionSheet = false"
      class="s-modal s-bottom-sheet"
    >
      <IonContent v-if="actionUser" class="s-modal-content">
        <div class="as-wrap">
          <div class="as-handle"></div>
          <!-- user mini header -->
          <div class="as-user-hdr">
            <div class="avatar" :style="avatarStyle(actionUser.role_key)" style="width:42px;height:42px;font-size:13px">
              {{ initials(actionUser.full_name || actionUser.name) }}
              <div class="av-dot" :class="actionUser.is_active ? 'dot-active' : 'dot-inactive'"></div>
            </div>
            <div>
              <div class="as-name">{{ actionUser.full_name || actionUser.name }}</div>
              <div class="as-sub">@{{ actionUser.username }} · {{ roleLabel(actionUser.role_key) }}</div>
            </div>
          </div>
          <div class="as-divider"></div>
          <!-- actions -->
          <button class="as-action" @click="viewDetail(actionUser)">
            <div class="as-action-icon" style="background:#EFF6FF">
              <svg viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
            <div class="as-action-text">
              <span class="as-action-label">View Full Profile</span>
              <span class="as-action-sub">All details, audit trail</span>
            </div>
            <svg viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="2" stroke-linecap="round" style="width:16px;height:16px;flex-shrink:0"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <button class="as-action" @click="openEditModal(actionUser); showActionSheet = false">
            <div class="as-action-icon" style="background:#FFFBEB">
              <svg viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>
            <div class="as-action-text">
              <span class="as-action-label">Edit User</span>
              <span class="as-action-sub">Update profile, role, assignment</span>
            </div>
            <svg viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="2" stroke-linecap="round" style="width:16px;height:16px;flex-shrink:0"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <button class="as-action" @click="toggleStatus(actionUser); showActionSheet = false">
            <div class="as-action-icon" :style="actionUser.is_active ? 'background:#FEF2F2' : 'background:#ECFDF5'">
              <svg viewBox="0 0 24 24" fill="none" :stroke="actionUser.is_active ? '#DC2626' : '#10B981'" stroke-width="2" stroke-linecap="round">
                <path v-if="actionUser.is_active" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                <path v-else d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div class="as-action-text">
              <span class="as-action-label" :style="actionUser.is_active ? 'color:#DC2626' : 'color:#10B981'">
                {{ actionUser.is_active ? 'Deactivate Account' : 'Activate Account' }}
              </span>
              <span class="as-action-sub">{{ actionUser.is_active ? 'Disable login access' : 'Restore login access' }}</span>
            </div>
          </button>
          <button v-if="actionUser.sync_status !== 'SYNCED'" class="as-action" @click="syncOne(actionUser); showActionSheet = false">
            <div class="as-action-icon" style="background:#F0FDF4">
              <svg viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round">
                <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
              </svg>
            </div>
            <div class="as-action-text">
              <span class="as-action-label">Upload to Server</span>
              <span class="as-action-sub">Sync this record now</span>
            </div>
          </button>
          <button class="as-cancel" @click="showActionSheet = false">Cancel</button>
        </div>
      </IonContent>
    </IonModal>

    <!-- Toast notification -->
    <IonToast
      :is-open="toast.show"
      :message="toast.msg"
      :duration="2800"
      :color="toast.color"
      position="top"
      @didDismiss="toast.show = false"
    />

  </IonPage>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonToolbar, IonContent,
  IonModal, IonRefresher, IonRefresherContent, IonToast,
} from '@ionic/vue'
import { chevronDownCircleOutline } from 'ionicons/icons'
import {
  dbPut, dbGet, dbGetAll, safeDbPut, dbGetByIndex,
  genUUID, isoNow, createRecordBase,
  STORE, SYNC, APP,
} from '@/services/poeDB'

const router = useRouter()

// ── AUTH ─────────────────────────────────────────────────────────────────
const auth = ref(null)

function getAuth() {
  try { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? null }
  catch { return null }
}

// ── POES.JS reference ────────────────────────────────────────────────────
const POE_DATA = window.POE_MAIN || { administrative_groups: [], poes: [] }

const PHEOC_LIST = [
  'Arua RPHEOC','Fort Portal RPHEOC','Gulu RPHEOC','Hoima RPHEOC',
  'Jinja RPHEOC','Kabale RPHEOC','Kampala RPHEOC','Masaka RPHEOC',
  'Mbale RPHEOC','National PHEOC',
]

// Build PHEOC → districts map from POES.JS
const PHEOC_DISTRICT_MAP = {}
;(POE_DATA.administrative_groups || []).forEach(g => {
  if (g.country === 'Uganda' && g.admin_level_1) {
    PHEOC_DISTRICT_MAP[g.admin_level_1] = g.districts || []
  }
})

// Build district → POEs map from POES.JS
const DISTRICT_POE_MAP = {}
;(POE_DATA.poes || []).forEach(p => {
  if (p.country === 'Uganda' && p.district) {
    if (!DISTRICT_POE_MAP[p.district]) DISTRICT_POE_MAP[p.district] = []
    DISTRICT_POE_MAP[p.district].push(p.poe_name)
  }
})

const ROLES = [
  { value: 'SCREENER',            label: 'Screener' },
  { value: 'DISTRICT_SUPERVISOR', label: 'District Supervisor' },
  { value: 'PHEOC_OFFICER',       label: 'PHEOC Officer' },
  { value: 'NATIONAL_ADMIN',      label: 'National Admin' },
]

const ROLE_GEO = {
  SCREENER:            ['province_or_pheoc', 'district_code', 'poe_code'],
  DISTRICT_SUPERVISOR: ['province_or_pheoc', 'district_code'],
  PHEOC_OFFICER:       ['province_or_pheoc'],
  NATIONAL_ADMIN:      [],
}

const roleChips = [
  { value: '', label: 'All Roles', cls: 'chip-all' },
  { value: 'SCREENER', label: 'Screener', cls: 'chip-p' },
  { value: 'DISTRICT_SUPERVISOR', label: 'District Sup.', cls: 'chip-d' },
  { value: 'PHEOC_OFFICER', label: 'PHEOC Officer', cls: 'chip-pheoc' },
  { value: 'NATIONAL_ADMIN', label: 'National Admin', cls: 'chip-na' },
]

const statusOpts = [
  { value: '', label: 'All', dotCls: '' },
  { value: '1', label: 'Active', dotCls: 'dot-active' },
  { value: '0', label: 'Inactive', dotCls: 'dot-inactive' },
]

// ── STATE ────────────────────────────────────────────────────────────────
const allUsers    = ref([])
const loading     = ref(false)
const searchQ     = ref('')
const activeRole  = ref('')
const activeStatus = ref('')
const activePoe   = ref('')
const serverPoes  = ref([])   // POEs extracted from server-returned user list
const expandedId  = ref(null)

// modals
const showFormModal    = ref(false)
const showDetailModal  = ref(false)
const showActionSheet  = ref(false)
const editingUser      = ref(null)
const detailUser       = ref(null)
const actionUser       = ref(null)

// form
const FORM_DEFAULTS = () => ({
  full_name: '', username: '', email: '', phone: '', password: '',
  role_key: 'SCREENER', is_active: true,
  country_code: 'UG',
  assignment: { country_code: 'UG', province_code: '', pheoc_code: '', district_code: '', poe_code: '', is_primary: true, is_active: true },
})
const form          = ref(FORM_DEFAULTS())
const errors        = ref({})
const formError     = ref('')
const formSuccess   = ref('')
const formSubmitting = ref(false)
const showPw        = ref(false)

// toast
const toast = ref({ show: false, msg: '', color: 'success' })

// sync
const activeSyncKeys = new Set()
let syncTimer = null
let searchTimer = null

// ── COMPUTED ─────────────────────────────────────────────────────────────
const syncedCount  = computed(() => allUsers.value.filter(u => u.sync_status === SYNC.SYNCED).length)
const pendingCount = computed(() => allUsers.value.filter(u => u.sync_status === SYNC.UNSYNCED).length)
const failedCount  = computed(() => allUsers.value.filter(u => u.sync_status === SYNC.FAILED).length)

const filteredUsers = computed(() => {
  let list = allUsers.value
  const q = searchQ.value.trim().toLowerCase()
  if (q) {
    list = list.filter(u =>
      (u.full_name || '').toLowerCase().includes(q) ||
      (u.name || '').toLowerCase().includes(q) ||
      (u.username || '').toLowerCase().includes(q) ||
      (u.email || '').toLowerCase().includes(q) ||
      (u.poe_code || '').toLowerCase().includes(q) ||
      (u.assignment?.poe_code || '').toLowerCase().includes(q) ||
      (u.assignment?.district_code || '').toLowerCase().includes(q)
    )
  }
  if (activeRole.value) list = list.filter(u => u.role_key === activeRole.value)
  if (activeStatus.value === '1') list = list.filter(u => u.is_active)
  if (activeStatus.value === '0') list = list.filter(u => !u.is_active)
  if (activePoe.value) list = list.filter(u => (u.poe_code || u.assignment?.poe_code) === activePoe.value)
  return list
})

const stripMode = computed(() => {
  if (pendingCount.value > 0) return 'pending'
  if (loading.value) return 'sync'
  return 'ok'
})

const stripClass = computed(() => {
  if (pendingCount.value > 0) return 'strip-warn'
  if (failedCount.value > 0) return 'strip-fail'
  return 'strip-ok'
})

const stripText = computed(() => {
  if (loading.value) return 'Loading users…'
  if (pendingCount.value > 0) return `${pendingCount.value} record${pendingCount.value > 1 ? 's' : ''} pending upload · Tap ↑ to sync`
  if (failedCount.value > 0) return `${failedCount.value} record${failedCount.value > 1 ? 's' : ''} queued (server rejected)`
  return `All ${allUsers.value.length} users uploaded · Last sync: ${isoNow().slice(11,16)}`
})

const filteredDistricts = computed(() => {
  const pheoc = form.value.assignment.province_code
  if (!pheoc) return []
  const list = PHEOC_DISTRICT_MAP[pheoc] || []
  // When editing: if the user existing district is not in the map, inject it so the select is not blank
  const current = form.value.assignment.district_code
  if (current && !list.includes(current)) return [current, ...list]
  return list
})

const filteredPoes = computed(() => {
  const dist = form.value.assignment.district_code
  if (!dist) return serverPoes.value.length ? serverPoes.value : []
  const fromData = DISTRICT_POE_MAP[dist] || []
  // When editing: if existing POE not in the list, inject it so select is not blank
  const current = form.value.assignment.poe_code
  const base = (() => {
    if (serverPoes.value.length) {
      const inter = fromData.filter(p => serverPoes.value.includes(p))
      return inter.length ? inter : fromData
    }
    return fromData
  })()
  if (current && !base.includes(current)) return [current, ...base]
  return base
})

// ── LIFECYCLE ─────────────────────────────────────────────────────────────
onMounted(async () => {
  auth.value = getAuth()
  await loadUsers()
  window.addEventListener('online', onOnline)
})

onUnmounted(() => {
  clearTimeout(syncTimer)
  clearTimeout(searchTimer)
  window.removeEventListener('online', onOnline)
})

function onOnline() {
  if (pendingCount.value > 0) syncAll()
}

// ── DATA LOADING ──────────────────────────────────────────────────────────
async function loadUsers() {
  loading.value = true
  // 1. try server first
  try {
    const params = new URLSearchParams({ per_page: '200' })
    if (activeRole.value) params.set('role_key', activeRole.value)
    if (activeStatus.value !== '') params.set('is_active', activeStatus.value)
    if (searchQ.value.trim()) params.set('search', searchQ.value.trim())

    const res = await fetch(`${window.SERVER_URL}/users?${params}`, {
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    if (res.ok) {
      const body = await res.json()
      const items = body?.data?.items ?? body?.data ?? []
      await cacheUsersLocally(items)
    }
  } catch (_) { /* offline — fall through to cache */ }

  // 2. read from IDB and deduplicate
  // ROOT CAUSE FIX: old code used `usr-{id}` keys; locally-created records used real UUIDs.
  // Both lived in IDB simultaneously → duplicates on screen.
  // Now: group all IDB records by server integer `id`.
  // For each server id keep only the record with the highest record_version.
  // Records with no server id yet (pending local creates) are kept as-is.
  const raw = await dbGetAll(STORE.USERS_LOCAL)

  const byId = new Map()     // server int id → best record
  const noId = []            // pending local creates (no server id yet)

  for (const r of raw) {
    const sid = r.id ?? r.server_user_id ?? r.server_id ?? null
    const norm = { ...r, id: sid ? Number(sid) : null }
    if (norm.id && Number.isInteger(norm.id) && norm.id > 0) {
      const prev = byId.get(norm.id)
      if (!prev || (norm.record_version ?? 0) >= (prev.record_version ?? 0)) {
        byId.set(norm.id, norm)
      }
    } else {
      noId.push(norm)
    }
  }

  allUsers.value = [
    ...Array.from(byId.values()).sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')),
    ...noId.sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')),
  ]

  // extract unique POEs from server-confirmed records only
  const poeSet = new Set()
  byId.forEach(u => {
    const p = u.poe_code || u.assignment?.poe_code
    if (p) poeSet.add(p)
  })
  serverPoes.value = Array.from(poeSet).sort()

  loading.value = false
}

async function cacheUsersLocally(items) {
  for (const u of items) {
    if (!u.id) continue                          // skip any record without a server id
    const idbKey = `srv-${u.id}`                 // FIX: deterministic key — same server record always overwrites same IDB slot
    const existing = await dbGet(STORE.USERS_LOCAL, idbKey).catch(() => null)
    await dbPut(STORE.USERS_LOCAL, {
      client_uuid:  idbKey,
      id:           u.id,              // LAW 3: server integer always as `id`
      role_key:     u.role_key,
      country_code: u.country_code,
      full_name:    u.full_name,
      name:         u.name,
      username:     u.username,
      username_ci:  (u.username || '').toLowerCase(),
      email:        u.email,
      email_ci:     (u.email || '').toLowerCase(),
      phone:        u.phone,
      is_active:    !!u.is_active,
      last_login_at: u.last_login_at,
      created_at:   u.created_at,
      updated_at:   u.updated_at,
      poe_code:     u.assignment?.poe_code ?? null,
      district_code: u.assignment?.district_code ?? null,
      province_code: u.assignment?.province_code ?? null,
      pheoc_code:   u.assignment?.pheoc_code ?? null,
      assignment:   u.assignment ?? null,
      sync_status:  SYNC.SYNCED,
      synced_at:    isoNow(),
      record_version: (existing?.record_version ?? 0) + 1,
    })
  }
}

// ── SEARCH ────────────────────────────────────────────────────────────────
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadUsers(), 350)
}

// ── FILTERS ──────────────────────────────────────────────────────────────
function setRole(v) { activeRole.value = v; loadUsers() }
function setStatus(v) { activeStatus.value = v; loadUsers() }

function onPheocChange() {
  form.value.assignment.district_code = ''
  form.value.assignment.poe_code = ''
  form.value.assignment.pheoc_code = form.value.assignment.province_code
}

function onDistrictChange() {
  form.value.assignment.poe_code = ''
}

// ── EXPAND ────────────────────────────────────────────────────────────────
function toggleExpand(user) {
  expandedId.value = expandedId.value === user.client_uuid ? null : user.client_uuid
}

// ── MODALS ────────────────────────────────────────────────────────────────
function openCreateModal() {
  editingUser.value = null
  form.value = FORM_DEFAULTS()
  errors.value = {}; formError.value = ''; formSuccess.value = ''
  showPw.value = false
  showFormModal.value = true
}

function openEditModal(user) {
  editingUser.value = user
  form.value = {
    full_name:    user.full_name || user.name || '',
    username:     user.username || '',
    email:        user.email || '',
    phone:        user.phone || '',
    password:     '',
    role_key:     user.role_key || 'SCREENER',
    is_active:    !!user.is_active,
    country_code: user.country_code || 'UG',
    assignment: {
      country_code:  user.assignment?.country_code || user.country_code || 'UG',
      province_code: user.assignment?.province_code || user.province_code || '',
      pheoc_code:    user.assignment?.pheoc_code || user.pheoc_code || '',
      district_code: user.assignment?.district_code || user.district_code || '',
      poe_code:      user.assignment?.poe_code || user.poe_code || '',
      is_primary:    user.assignment?.is_primary ?? true,
      is_active:     user.assignment?.is_active ?? true,
    },
  }
  errors.value = {}; formError.value = ''; formSuccess.value = ''
  showPw.value = false
  showFormModal.value = true
}

function closeFormModal() { showFormModal.value = false; editingUser.value = null }

function viewDetail(user) {
  detailUser.value = user
  showDetailModal.value = true
  showActionSheet.value = false
}

function closeDetailModal() { showDetailModal.value = false; detailUser.value = null }

function openActionSheet(user) {
  actionUser.value = user
  showActionSheet.value = true
}

// ── FORM VALIDATION ───────────────────────────────────────────────────────
function geoRequired(field) {
  const reqs = ROLE_GEO[form.value.role_key] || []
  if (field === 'province_or_pheoc') return reqs.includes('province_or_pheoc')
  if (field === 'district_code')     return reqs.includes('district_code')
  if (field === 'poe_code')          return reqs.includes('poe_code')
  return false
}

function validateForm() {
  const e = {}
  if (!form.value.full_name || form.value.full_name.trim().length < 2) e.full_name = 'Full name required (min 2 chars)'
  if (!form.value.username || form.value.username.trim().length < 4) e.username = 'Username required (min 4 chars)'
  if (!/^[a-zA-Z0-9._-]+$/.test(form.value.username)) e.username = 'Only letters, numbers, dots, underscores, hyphens'
  if (!form.value.role_key) e.role_key = 'Role is required'
  if (!editingUser.value && (!form.value.password || form.value.password.length < 8)) e.password = 'Password required, min 8 chars'
  if (editingUser.value && form.value.password && form.value.password.length < 8) e.password = 'Password must be at least 8 chars'
  if (form.value.email && !/^\S+@\S+\.\S+$/.test(form.value.email)) e.email = 'Invalid email format'
  if (geoRequired('province_or_pheoc') && !form.value.assignment.province_code) e['assignment.province_code'] = 'PHEOC/Province required for this role'
  if (geoRequired('district_code') && !form.value.assignment.district_code) e['assignment.district_code'] = 'District required for this role'
  if (geoRequired('poe_code') && !form.value.assignment.poe_code) e['assignment.poe_code'] = 'POE required for Screener role'
  errors.value = e
  return Object.keys(e).length === 0
}

// ── SUBMIT FORM ───────────────────────────────────────────────────────────
async function submitForm() {
  formError.value = ''; formSuccess.value = ''
  if (!validateForm()) return

  formSubmitting.value = true
  const freshAuth = getAuth()
  const uuid = editingUser.value?.client_uuid || genUUID()
  const pheoc = form.value.assignment.province_code

  const payload = {
    client_uuid:  uuid,
    full_name:    form.value.full_name.trim(),
    username:     form.value.username.trim().toLowerCase(),
    email:        form.value.email?.trim() || null,
    phone:        form.value.phone?.trim() || null,
    role_key:     form.value.role_key,
    country_code: 'UG',
    is_active:    form.value.is_active ? 1 : 0,
    assignment: {
      country_code:  'UG',
      province_code: form.value.assignment.province_code || null,
      pheoc_code:    pheoc || null,
      district_code: form.value.assignment.district_code || null,
      poe_code:      form.value.assignment.poe_code || null,
      is_primary:    1,
      is_active:     1,
    },
  }
  if (form.value.password) payload.password = form.value.password

  // Build local record for immediate offline save
  const localRecord = {
    client_uuid:   uuid,
    id:            editingUser.value?.id ?? null,
    role_key:      payload.role_key,
    country_code:  'UG',
    full_name:     payload.full_name,
    name:          payload.full_name,
    username:      payload.username,
    username_ci:   payload.username.toLowerCase(),
    email:         payload.email,
    email_ci:      (payload.email || '').toLowerCase(),
    phone:         payload.phone,
    is_active:     form.value.is_active,
    created_at:    editingUser.value?.created_at || isoNow(),
    updated_at:    isoNow(),
    last_login_at: editingUser.value?.last_login_at || null,
    poe_code:      payload.assignment.poe_code,
    district_code: payload.assignment.district_code,
    province_code: payload.assignment.province_code,
    pheoc_code:    payload.assignment.pheoc_code,
    assignment:    payload.assignment,
    sync_status:   SYNC.UNSYNCED,
    synced_at:     null,
    sync_attempt_count: 0,
    record_version: (editingUser.value?.record_version || 0) + 1,
  }

  // Save locally first (offline-first guarantee)
  if (editingUser.value) {
    await safeDbPut(STORE.USERS_LOCAL, localRecord)
  } else {
    await dbPut(STORE.USERS_LOCAL, localRecord)
  }

  // Reload list immediately to show the new/updated card
  await loadUsers()

  // Attempt server sync
  try {
    const isEdit = !!editingUser.value?.id
    // LAW 1: use integer id for URL, no UUID
    const url = isEdit
      ? `${window.SERVER_URL}/users/${editingUser.value.id}`
      : `${window.SERVER_URL}/users`
    const method = isEdit ? 'PATCH' : 'POST'

    const ctrl = new AbortController()
    const tid = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
    let res
    try {
      res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
        signal: ctrl.signal,
      })
    } finally {
      clearTimeout(tid)
    }

    if (res.ok) {
      const body = await res.json()
      const serverRecord = body?.data ?? body
      const serverId = serverRecord?.id ?? null

      await safeDbPut(STORE.USERS_LOCAL, {
        ...localRecord,
        id:           serverId,
        sync_status:  SYNC.SYNCED,
        synced_at:    isoNow(),
        record_version: localRecord.record_version + 1,
        updated_at:   isoNow(),
      })
      formSuccess.value = isEdit ? 'User updated and uploaded.' : 'User created and uploaded.'
      showToast(formSuccess.value, 'success')
      await loadUsers()
      setTimeout(() => closeFormModal(), 1200)
    } else {
      const errBody = await res.json().catch(() => ({}))
      const msg = errBody?.message || errBody?.errors
        ? Object.values(errBody.errors || {}).flat().join(' ') || errBody?.message
        : `Server error ${res.status}`
      await safeDbPut(STORE.USERS_LOCAL, {
        ...localRecord,
        sync_status:  res.status >= 500 ? SYNC.UNSYNCED : SYNC.FAILED,
        last_sync_error: String(msg),
        record_version: localRecord.record_version + 1,
      })
      if (res.status === 422 && errBody?.errors) {
        // Map server validation errors back to form fields
        const e = {}
        Object.entries(errBody.errors).forEach(([k, v]) => { e[k] = Array.isArray(v) ? v[0] : v })
        errors.value = { ...errors.value, ...e }
        formError.value = 'Please fix the highlighted fields.'
      } else {
        formSuccess.value = 'Saved offline. Will upload when connection improves.'
        showToast(formSuccess.value, 'warning')
        setTimeout(() => closeFormModal(), 1400)
      }
      await loadUsers()
    }
  } catch (e) {
    formSuccess.value = 'Saved offline. Will upload when online.'
    showToast(formSuccess.value, 'warning')
    await loadUsers()
    setTimeout(() => closeFormModal(), 1400)
  }

  formSubmitting.value = false
}

// ── TOGGLE STATUS ─────────────────────────────────────────────────────────
async function toggleStatus(user) {
  const newStatus = !user.is_active

  // Optimistic local update
  const updated = {
    ...user,
    is_active:     newStatus,
    updated_at:    isoNow(),
    sync_status:   SYNC.UNSYNCED,
    record_version: (user.record_version || 1) + 1,
  }
  await safeDbPut(STORE.USERS_LOCAL, updated)
  await loadUsers()

  // Must have integer id for PATCH — LAW 1
  if (!Number.isInteger(Number(user.id)) || Number(user.id) <= 0) {
    showToast('Status updated offline. Sync required to push to server.', 'warning')
    return
  }

  try {
    const res = await fetch(`${window.SERVER_URL}/users/${user.id}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ is_active: newStatus, client_uuid: user.client_uuid }),
    })
    if (res.ok) {
      await safeDbPut(STORE.USERS_LOCAL, {
        ...updated,
        sync_status:  SYNC.SYNCED,
        synced_at:    isoNow(),
        record_version: (updated.record_version || 1) + 1,
      })
      showToast(newStatus ? 'User activated.' : 'User deactivated.', 'success')
      await loadUsers()
    } else {
      showToast('Status saved offline. Server update failed — will retry.', 'warning')
    }
  } catch {
    showToast('Status saved offline.', 'warning')
  }
}

// ── SYNC ENGINE ───────────────────────────────────────────────────────────
async function syncOne(user) {
  const uuid = user.client_uuid
  if (activeSyncKeys.has(uuid)) return false
  activeSyncKeys.add(uuid)

  try {
    const record = await dbGet(STORE.USERS_LOCAL, uuid)
    if (!record || record.sync_status === SYNC.SYNCED) return true

    // Must have integer id to PATCH, otherwise use POST
    const isUpdate = Number.isInteger(Number(record.id)) && Number(record.id) > 0
    const url = isUpdate
      ? `${window.SERVER_URL}/users/${record.id}`
      : `${window.SERVER_URL}/users`
    const method = isUpdate ? 'PATCH' : 'POST'

    const working = {
      ...record,
      sync_attempt_count: (record.sync_attempt_count || 0) + 1,
      record_version: (record.record_version || 1) + 1,
      updated_at: isoNow(),
    }
    await safeDbPut(STORE.USERS_LOCAL, working)

    const payload = buildSyncPayload(working)
    const ctrl = new AbortController()
    const tid = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
    let res
    try {
      res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
        signal: ctrl.signal,
      })
    } finally { clearTimeout(tid) }

    if (res.ok) {
      const body = await res.json()
      const serverId = body?.data?.id ?? null
      await safeDbPut(STORE.USERS_LOCAL, {
        ...working,
        id:           serverId ?? working.id,
        sync_status:  SYNC.SYNCED,
        synced_at:    isoNow(),
        record_version: (working.record_version || 1) + 1,
        updated_at:   isoNow(),
      })
      await loadUsers()
      showToast('User uploaded successfully.', 'success')
      return true
    }

    const retryable = res.status >= 500 || res.status === 429
    const errBody = await res.json().catch(() => ({}))
    const errMsg = errBody?.message || `HTTP ${res.status}`
    await safeDbPut(STORE.USERS_LOCAL, {
      ...working,
      sync_status:  retryable ? SYNC.UNSYNCED : SYNC.FAILED,
      last_sync_error: errMsg,
      record_version: (working.record_version || 1) + 1,
    })
    if (retryable) scheduleRetry()
    await loadUsers()
    return false
  } catch (e) {
    const latest = await dbGet(STORE.USERS_LOCAL, uuid).catch(() => null)
    if (latest) {
      await safeDbPut(STORE.USERS_LOCAL, {
        ...latest,
        sync_status: SYNC.UNSYNCED,
        sync_attempt_count: (latest.sync_attempt_count || 0) + 1,
        record_version: (latest.record_version || 1) + 1,
        updated_at: isoNow(),
      })
    }
    scheduleRetry()
    return false
  } finally {
    activeSyncKeys.delete(uuid)
  }
}

function buildSyncPayload(r) {
  return {
    client_uuid:  r.client_uuid,
    full_name:    r.full_name || r.name,
    username:     r.username,
    email:        r.email || null,
    phone:        r.phone || null,
    role_key:     r.role_key,
    country_code: r.country_code || 'UG',
    is_active:    r.is_active ? 1 : 0,
    assignment: {
      country_code:  r.assignment?.country_code || r.country_code || 'UG',
      province_code: r.assignment?.province_code || r.province_code || null,
      pheoc_code:    r.assignment?.pheoc_code || r.pheoc_code || null,
      district_code: r.assignment?.district_code || r.district_code || null,
      poe_code:      r.assignment?.poe_code || r.poe_code || null,
      is_primary:    1,
      is_active:     1,
    },
  }
}

async function syncAll() {
  const pending = allUsers.value.filter(u => u.sync_status !== SYNC.SYNCED)
  for (const u of pending) await syncOne(u)
}

function scheduleRetry() {
  clearTimeout(syncTimer)
  syncTimer = setTimeout(() => {
    if (navigator.onLine && pendingCount.value > 0) syncAll()
    else if (pendingCount.value > 0) scheduleRetry()
  }, APP.SYNC_RETRY_MS)
}

// ── PULL TO REFRESH ───────────────────────────────────────────────────────
async function onRefresh(ev) {
  await loadUsers()
  ev.target.complete()
}

// ── NAVIGATION ────────────────────────────────────────────────────────────
function goBack() { router.back() }

// ── TOAST ─────────────────────────────────────────────────────────────────
function showToast(msg, color = 'success') {
  toast.value = { show: true, msg, color }
}

// ── HELPERS ──────────────────────────────────────────────────────────────
function initials(name) {
  if (!name) return '?'
  return name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

function fmtDate(dt) {
  if (!dt) return ''
  try {
    return new Date(dt).toLocaleString('en-UG', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch { return dt }
}

const ROLE_META = {
  SCREENER:            { label: 'Screener',         accent: '#2563EB', bg: '#EFF6FF', text: '#1D4ED8', border: '#BFDBFE' },
  DISTRICT_SUPERVISOR: { label: 'District Sup.',    accent: '#EA580C', bg: '#FFF7ED', text: '#C2410C', border: '#FDBA74' },
  PHEOC_OFFICER:       { label: 'PHEOC Officer',    accent: '#BE185D', bg: '#FDF2F8', text: '#9D174D', border: '#FBCFE8' },
  NATIONAL_ADMIN:      { label: 'National Admin',   accent: '#DC2626', bg: '#FEF2F2', text: '#B91C1C', border: '#FECACA' },
}

function roleMeta(rk) { return ROLE_META[rk] || { label: rk || '—', accent: '#64748B', bg: '#F8FAFC', text: '#475569', border: '#E2E8F0' } }
function roleLabel(rk)  { return roleMeta(rk).label }
function roleAccent(rk, alpha) {
  const hex = roleMeta(rk).accent
  if (!alpha) return hex
  // Return rgba approximation from hex
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16)
  return `rgba(${r},${g},${b},${alpha})`
}
function roleBadgeStyle(rk) {
  const m = roleMeta(rk)
  return { background: m.bg, color: m.text, borderColor: m.border, border: '1px solid' }
}
function avatarStyle(rk, large) {
  const m = roleMeta(rk)
  const sz = large ? '64px' : '46px'
  const fs = large ? '18px' : '14px'
  return { background: m.bg, color: m.text, width: sz, height: sz, fontSize: fs }
}
function roleColor(rk, alpha) { return roleAccent(rk, alpha) }

function syncLabel(s)    { return SYNC.LABELS?.[s] ?? s }
function syncPillCls(s)  { return s === SYNC.SYNCED ? 'sp-synced' : s === SYNC.FAILED ? 'sp-failed' : 'sp-pending' }
function syncDotCls(s)   { return s === SYNC.SYNCED ? 'sd-ok' : s === SYNC.FAILED ? 'sd-fail' : 'sd-warn' }
</script>

<style scoped>
/* ═══════════════════════════════════════════════
   RESET / TOKENS
════════════════════════════════════════════════ */
* { box-sizing: border-box; }
:root, :host {
  --clr-bg:      #EEF2FF;
  --clr-hdr:     #0A0F1E;
  --clr-card:    #FFFFFF;
  --clr-border:  rgba(0,0,0,.07);
  --clr-txt1:    #0F172A;
  --clr-txt2:    #64748B;
  --clr-txt3:    #94A3B8;
}

/* ═══════════════════════════════════════════════
   HEADER / TOOLBAR
════════════════════════════════════════════════ */
.s-header { position: sticky; top: 0; z-index: 100; }
.s-toolbar {
  --background: #0A0F1E;
  --padding-start: 0; --padding-end: 0; --padding-top: 0; --padding-bottom: 0;
  --min-height: auto;
}

.hdr-top {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 18px 14px;
}
.hdr-left { display: flex; align-items: center; gap: 10px; }
.back-btn {
  width: 34px; height: 34px; border-radius: 50%;
  background: rgba(255,255,255,.09); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.back-btn svg { width: 18px; height: 18px; stroke: #A8B8D8; }
.eyebrow {
  font-size: 9.5px; font-weight: 700; letter-spacing: 2px; color: #4A6FA5;
  text-transform: uppercase; display: block; line-height: 1;
}
.page-title { font-size: 20px; font-weight: 800; color: #FFFFFF; letter-spacing: -.3px; margin-top: 2px; }

.hdr-right { display: flex; align-items: center; gap: 8px; }
.hbtn {
  width: 38px; height: 38px; border-radius: 12px;
  background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1);
  display: flex; align-items: center; justify-content: center;
  position: relative; cursor: pointer; flex-shrink: 0;
}
.hbtn svg { width: 18px; height: 18px; stroke: #A8B8D8; }
.hbtn-warn { border-color: rgba(245,158,11,.35); animation: pulse 2s infinite; }
.hbtn-blue { background: rgba(37,99,235,.2); border-color: rgba(37,99,235,.4); }
.badge {
  position: absolute; top: -4px; right: -4px;
  background: #F59E0B; color: #000; font-size: 9px; font-weight: 900;
  min-width: 16px; height: 16px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  padding: 0 4px; border: 2px solid #0A0F1E;
}
@keyframes pulse { 0%,100% { opacity:1 } 50% { opacity:.5 } }

/* STRIP */
.strip {
  padding: 7px 18px; display: flex; align-items: center; gap: 6px;
}
.strip-icon { width: 13px; height: 13px; flex-shrink: 0; }
.strip-txt { font-size: 11.5px; font-weight: 600; letter-spacing: .2px; }
.strip-ok   { background: #1C6B3A; }
.strip-ok .strip-txt { color: #86EFAC; }
.strip-ok .strip-icon { stroke: #86EFAC; }
.strip-warn { background: #7C3B00; }
.strip-warn .strip-txt { color: #FCD34D; }
.strip-warn .strip-icon { stroke: #FCD34D; }
.strip-fail { background: #7F1D1D; }
.strip-fail .strip-txt { color: #FCA5A5; }
.strip-fail .strip-icon { stroke: #FCA5A5; }

/* STATS */
.stats-row {
  display: grid; grid-template-columns: repeat(4,1fr);
  gap: 1px; background: #1A2540;
}
.stat-cell { background: #111929; padding: 10px 0 9px; text-align: center; position: relative; }
.stat-cell:not(:last-child)::after {
  content:''; position:absolute; right:0; top:20%; height:60%; width:1px; background:#1A2540;
}
.stat-n { display: block; font-size: 20px; font-weight: 900; line-height: 1; letter-spacing: -.5px; }
.stat-l { display: block; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; color: #4A6FA5; margin-top: 3px; }
.n-total { color: #E8EDF8; }
.n-ok    { color: #34D399; }
.n-warn  { color: #F59E0B; }
.n-fail  { color: #F87171; }

/* SEARCH */
.search-area { background: #0A0F1E; padding: 10px 16px 8px; }
.search-wrap {
  background: #111929; border: 1px solid #1E2A4A; border-radius: 14px;
  display: flex; align-items: center; gap: 10px; padding: 0 14px; height: 44px;
}
.search-ic { width: 16px; height: 16px; flex-shrink: 0; }
.search-input {
  flex: 1; font-size: 14px; color: #CBD5E1; background: transparent;
  border: none; outline: none; letter-spacing: .1px;
}
.search-input::placeholder { color: #4A6FA5; }
.search-clear { background: transparent; border: none; cursor: pointer; display: flex; }
.search-clear svg { width: 16px; height: 16px; }

/* CHIPS */
.chips-row {
  background: #0A0F1E; padding: 0 14px 12px;
  display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none;
}
.chips-row::-webkit-scrollbar { display: none; }
.chip {
  padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
  letter-spacing: .4px; text-transform: uppercase; white-space: nowrap;
  border: 1px solid; cursor: pointer; flex-shrink: 0; background: transparent;
  transition: all .15s;
}
.chip-all  { color: #93C5FD; border-color: rgba(37,99,235,.3); }
.chip-p    { color: #60A5FA; border-color: rgba(37,99,235,.2); }
.chip-d    { color: #FCA893; border-color: rgba(234,88,12,.2); }
.chip-pheoc{ color: #F9A8D4; border-color: rgba(190,24,93,.2); }
.chip-na   { color: #FCA5A5; border-color: rgba(220,38,38,.2); }
.chip-active { background: rgba(255,255,255,.12) !important; border-width: 1.5px !important; }

/* STATUS TOGGLE */
.status-toggle-row {
  background: #0A0F1E; padding: 0 16px 14px;
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.stog-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .4px; cursor: pointer;
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  color: #6B7280; transition: all .15s;
}
.stog-active-on  { background: rgba(16,185,129,.15); border-color: rgba(16,185,129,.4); color: #34D399; }
.stog-active-off { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.35); color: #FCA5A5; }
.poe-select {
  margin-left: auto; padding: 5px 10px; border-radius: 10px;
  background: #111929; border: 1px solid #1E2A4A; color: #CBD5E1;
  font-size: 11px; font-weight: 600; outline: none; cursor: pointer;
}

/* ═══════════════════════════════════════════════
   CONTENT
════════════════════════════════════════════════ */
.s-content { --background: #EEF2FF; }
.content-wrap { padding: 14px 14px; display: flex; flex-direction: column; gap: 10px; }

/* POE BANNER */
.poe-banner {
  background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 12px;
  padding: 9px 12px; display: flex; align-items: center; gap: 8px;
}
.poe-banner svg { width: 14px; height: 14px; flex-shrink: 0; }
.poe-banner-txt { font-size: 11.5px; color: #1D4ED8; font-weight: 500; line-height: 1.4; }
.poe-banner-txt strong { font-weight: 800; }

/* SECTION HEADER */
.sec-hdr { display: flex; align-items: center; justify-content: space-between; padding: 2px 0; }
.sec-lbl { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; color: #94A3B8; padding-left: 2px; }
.sec-count { font-size: 11px; font-weight: 700; color: #64748B; background: #F1F5F9; padding: 2px 8px; border-radius: 6px; }

/* SKELETON */
.skel-card { background: #fff; border-radius: 18px; overflow: hidden; border: 1px solid #F1F5F9; }
.skel-accent { height: 4px; background: #E2E8F0; }
.skel-body { padding: 14px; display: flex; gap: 12px; }
.skel-avatar { width: 46px; height: 46px; border-radius: 14px; background: #F1F5F9; flex-shrink: 0; animation: shimmer 1.4s infinite; }
.skel-lines { flex: 1; }
.skel-line { height: 13px; background: #F1F5F9; border-radius: 6px; animation: shimmer 1.4s infinite; }
@keyframes shimmer { 0%,100% { opacity:1 } 50% { opacity:.5 } }

/* EMPTY STATE */
.empty-state { padding: 48px 24px; text-align: center; }
.empty-icon { width: 72px; height: 72px; background: #F1F5F9; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.empty-icon svg { width: 32px; height: 32px; }
.empty-title { font-size: 17px; font-weight: 800; color: #334155; margin-bottom: 6px; }
.empty-sub { font-size: 13px; color: #94A3B8; margin-bottom: 20px; }
.empty-btn {
  padding: 10px 28px; background: #2563EB; color: #fff;
  border: none; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer;
}

/* USER CARD */
.ucard {
  background: #FFFFFF; border-radius: 18px; overflow: hidden;
  border: 1px solid rgba(0,0,0,.06); position: relative;
  transition: border-color .2s, box-shadow .2s;
}
.ucard-inactive { opacity: .65; }
.ucard-accent { height: 4px; width: 100%; }
.ucard-body {
  padding: 14px 14px 12px; display: flex; align-items: flex-start; gap: 12px; cursor: pointer;
}

.avatar {
  border-radius: 14px; display: flex; align-items: center; justify-content: center;
  font-weight: 900; letter-spacing: -.5px; flex-shrink: 0; position: relative;
}
.av-dot {
  width: 11px; height: 11px; border-radius: 50%;
  position: absolute; bottom: -2px; right: -2px; border: 2px solid #fff;
}
.dot-active   { background: #10B981; }
.dot-inactive { background: #9CA3AF; }

.ucard-info { flex: 1; min-width: 0; }
.ucard-name {
  font-size: 15px; font-weight: 800; color: #0F172A;
  letter-spacing: -.3px; line-height: 1.2;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ucard-username { font-size: 11.5px; color: #64748B; font-weight: 500; margin-top: 2px; }
.inactive-label { color: #DC2626; font-size: 10px; font-weight: 700; margin-left: 4px; }
.ucard-tags { display: flex; align-items: center; gap: 5px; margin-top: 8px; flex-wrap: wrap; }

.role-badge {
  padding: 3px 9px; border-radius: 7px; font-size: 10px; font-weight: 800;
  letter-spacing: .5px; text-transform: uppercase;
}
.poe-tag {
  padding: 3px 8px; border-radius: 7px; font-size: 10px; font-weight: 600;
  background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;
  display: flex; align-items: center; gap: 3px;
}
.poe-tag svg { width: 9px; height: 9px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

.ucard-right { display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; gap: 8px; padding-top: 2px; }
.sync-pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 9px; border-radius: 8px; font-size: 10px; font-weight: 700; letter-spacing: .3px;
}
.sp-synced  { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.sp-pending { background: #FFFBEB; color: #92400E; border: 1px solid #FCD34D; }
.sp-failed  { background: #FEF2F2; color: #7F1D1D; border: 1px solid #FCA5A5; }
.sync-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.sd-ok   { background: #10B981; }
.sd-warn { background: #F59E0B; }
.sd-fail { background: #EF4444; }

.ucard-action {
  width: 28px; height: 28px; border-radius: 8px;
  background: #F8FAFC; border: 1px solid #E2E8F0;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.ucard-action svg { width: 13px; height: 13px; }

/* EXPAND */
.ucard-details {
  border-top: 1px solid #F1F5F9; margin: 0 14px; padding: 10px 0 12px;
  display: flex; flex-direction: column; gap: 7px;
}
.det-section-title {
  font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px;
  color: #94A3B8; margin-top: 4px;
}
.det-row { display: flex; align-items: center; justify-content: space-between; }
.det-k {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
  color: #94A3B8; display: flex; align-items: center; gap: 5px;
}
.det-k svg { width: 11px; height: 11px; flex-shrink: 0; }
.det-v { font-size: 12px; font-weight: 600; color: #334155; }
.det-small { font-size: 11px; color: #64748B; font-weight: 500; }
.det-mono { font-family: monospace; letter-spacing: .3px; color: #334155; }

.expand-enter-active, .expand-leave-active { transition: max-height .25s ease, opacity .2s; overflow: hidden; }
.expand-enter-from, .expand-leave-to { max-height: 0; opacity: 0; }
.expand-enter-to, .expand-leave-from { max-height: 400px; opacity: 1; }

/* INLINE ACTIONS */
.inline-actions { display: flex; gap: 8px; margin-top: 8px; }
.ia-btn {
  display: flex; align-items: center; gap: 5px;
  padding: 7px 12px; border-radius: 10px; font-size: 11px; font-weight: 700;
  cursor: pointer; border: 1px solid; transition: all .15s;
}
.ia-btn svg { width: 13px; height: 13px; flex-shrink: 0; }
.ia-edit  { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
.ia-deact { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
.ia-act   { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }
.ia-sync  { background: #F0FDF4; color: #15803D; border-color: #BBF7D0; }

/* FAB */
.fab-container { position: fixed; bottom: 32px; right: 20px; z-index: 200; }
.fab {
  width: 56px; height: 56px; background: #2563EB; border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  border: none; cursor: pointer;
  box-shadow: 0 4px 0 #1D4ED8, 0 8px 24px rgba(37,99,235,.45);
}

/* ═══════════════════════════════════════════════
   MODAL STYLES
════════════════════════════════════════════════ */
.s-modal { --height: 100%; --border-radius: 24px 24px 0 0; }
.s-modal-header { --background: #0A0F1E; }
.s-modal-toolbar {
  --background: #0A0F1E;
  --padding-start: 0; --padding-end: 0; --padding-top: 0; --padding-bottom: 0;
  --min-height: auto;
}
.s-modal-content { --background: #EEF2FF; }
.s-bottom-sheet { --height: auto; }

.modal-hdr {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 20px 16px;
}
.modal-title { font-size: 20px; font-weight: 800; color: #FFFFFF; letter-spacing: -.3px; margin-top: 2px; }
.modal-close {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,.1); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.modal-close svg { width: 18px; height: 18px; stroke: #A8B8D8; }

/* FORM */
.form-wrap { padding: 20px 18px; }
.form-section-title {
  font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.4px;
  color: #94A3B8; margin-bottom: 12px;
}
.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.form-group.form-error .form-input,
.form-group.form-error .form-select { border-color: #EF4444; }
.form-label { font-size: 12px; font-weight: 700; color: #475569; letter-spacing: .2px; }
.req { color: #EF4444; }
.form-input {
  padding: 12px 14px; background: #FFFFFF; border: 1.5px solid #E2E8F0;
  border-radius: 12px; font-size: 14px; color: #0F172A; outline: none;
  transition: border-color .15s;
}
.form-input:focus { border-color: #3B82F6; }
.form-input-readonly { background: #F8FAFC; color: #64748B; cursor: default; }
.form-select {
  padding: 12px 14px; background: #FFFFFF; border: 1.5px solid #E2E8F0;
  border-radius: 12px; font-size: 14px; color: #0F172A; outline: none;
  cursor: pointer; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 14px center;
  padding-right: 36px;
}
.form-err-msg { font-size: 11px; color: #EF4444; font-weight: 600; }
.pw-wrap { position: relative; }
.pw-input { width: 100%; padding-right: 44px; }
.pw-toggle {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: transparent; border: none; cursor: pointer; display: flex;
}
.pw-toggle svg { width: 18px; height: 18px; }

/* ROLE GRID */
.role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.role-opt {
  padding: 10px 12px; background: #FFFFFF; border: 1.5px solid #E2E8F0;
  border-radius: 12px; font-size: 12px; font-weight: 700; color: #475569;
  cursor: pointer; text-align: center; transition: all .15s;
}

/* TOGGLE ROW */
.toggle-row { display: flex; gap: 8px; }
.tog-btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 10px; border-radius: 12px; font-size: 12px; font-weight: 700;
  cursor: pointer; border: 1.5px solid #E2E8F0; background: #fff; color: #475569;
  transition: all .15s;
}
.tog-active-on  { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
.tog-active-off { background: #FEF2F2; border-color: #FECACA; color: #B91C1C; }

/* GEO HINT */
.geo-hint {
  display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px;
  background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 10px;
  margin-bottom: 14px; font-size: 12px; color: #1D4ED8; font-weight: 500; line-height: 1.4;
}
.geo-hint svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 1px; }

/* ALERTS */
.form-alert-error {
  display: flex; align-items: flex-start; gap: 8px; padding: 12px 14px;
  background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px;
  font-size: 13px; color: #B91C1C; font-weight: 600; margin-bottom: 14px; line-height: 1.4;
}
.form-alert-error svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
.form-alert-ok {
  display: flex; align-items: center; gap: 8px; padding: 12px 14px;
  background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 10px;
  font-size: 13px; color: #065F46; font-weight: 600; margin-bottom: 14px;
}
.form-alert-ok svg { width: 16px; height: 16px; flex-shrink: 0; }

/* SUBMIT */
.submit-btn {
  width: 100%; padding: 16px; background: #2563EB; color: #fff;
  border: none; border-radius: 14px; font-size: 15px; font-weight: 800;
  letter-spacing: .2px; cursor: pointer; display: flex; align-items: center;
  justify-content: center; gap: 8px; box-shadow: 0 4px 0 #1D4ED8;
  transition: transform .1s;
}
.submit-btn:disabled { opacity: .6; cursor: default; }
.submit-btn svg { width: 18px; height: 18px; }
.spinner {
  width: 18px; height: 18px; border: 2.5px solid rgba(255,255,255,.3);
  border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* DETAIL MODAL */
.detail-wrap { padding: 0 0 20px; }
.detail-hero {
  background: #fff; border-top: 4px solid #2563EB;
  padding: 20px 20px 18px; display: flex; align-items: flex-start; gap: 16px;
  margin-bottom: 2px;
}
.detail-avatar {
  border-radius: 18px; display: flex; align-items: center; justify-content: center;
  font-weight: 900; flex-shrink: 0;
}
.detail-hero-info { flex: 1; min-width: 0; }
.detail-hero-name { font-size: 18px; font-weight: 800; color: #0F172A; letter-spacing: -.3px; line-height: 1.2; }
.detail-hero-sub  { font-size: 12px; color: #64748B; font-weight: 500; margin-top: 4px; }

.detail-section { background: #fff; margin: 2px 14px; border-radius: 16px; padding: 16px; }
.detail-sec-title {
  font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.4px;
  color: #94A3B8; margin-bottom: 12px;
}
.detail-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 7px 0; border-bottom: 1px solid #F8FAFC;
}
.detail-row:last-child { border-bottom: none; }
.det-k {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
  color: #94A3B8;
}
.det-v { font-size: 13px; font-weight: 600; color: #334155; text-align: right; max-width: 60%; }
.det-mono { font-family: monospace; }

.detail-actions {
  display: flex; flex-direction: column; gap: 10px;
  padding: 16px 14px 0;
}
.det-act-btn {
  width: 100%; padding: 14px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  font-size: 14px; font-weight: 700; cursor: pointer; border: 1.5px solid;
  transition: all .15s;
}
.det-act-btn svg { width: 16px; height: 16px; }
.det-act-edit  { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
.det-act-deact { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
.det-act-act   { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }
.det-act-sync  { background: #F0FDF4; color: #15803D; border-color: #BBF7D0; }

/* ACTION SHEET */
.as-wrap { padding: 12px 16px 20px; }
.as-handle { width: 40px; height: 4px; background: #E2E8F0; border-radius: 2px; margin: 0 auto 16px; }
.as-user-hdr { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.as-name { font-size: 15px; font-weight: 800; color: #0F172A; letter-spacing: -.2px; }
.as-sub  { font-size: 11.5px; color: #64748B; font-weight: 500; margin-top: 2px; }
.as-divider { height: 1px; background: #F1F5F9; margin-bottom: 10px; }

.as-action {
  width: 100%; display: flex; align-items: center; gap: 12px;
  padding: 12px; border-radius: 14px; background: #F8FAFC; border: 1px solid #F1F5F9;
  cursor: pointer; margin-bottom: 8px; text-align: left; transition: background .12s;
}
.as-action:hover { background: #F1F5F9; }
.as-action-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.as-action-icon svg { width: 18px; height: 18px; }
.as-action-text { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.as-action-label { font-size: 14px; font-weight: 700; color: #0F172A; }
.as-action-sub   { font-size: 11.5px; color: #64748B; font-weight: 500; }
.as-cancel {
  width: 100%; padding: 14px; background: #F1F5F9; border: none; border-radius: 14px;
  font-size: 14px; font-weight: 700; color: #64748B; cursor: pointer; margin-top: 4px;
}
</style>