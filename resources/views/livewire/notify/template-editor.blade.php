<div class="dot-card" style="padding:1.5rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0;">Templates</h3>
        <button wire:click="$toggle('showForm')" class="dot-btn dot-btn-ghost" style="font-size:12px;padding:5px 12px;">
            <span class="material-symbols-rounded" style="font-size:14px;">auto_awesome</span>
            {{ $showForm ? 'Cancel' : 'AI Generate' }}
        </button>
    </div>

    @if($showForm)
    <div style="background:rgba(251,146,60,0.05);border:1px solid rgba(251,146,60,0.15);border-radius:8px;padding:1rem;margin-bottom:1.25rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
            <div>
                <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Purpose</div>
                <input wire:model="purpose" type="text" placeholder="Payment failed notification" class="dot-input" />
                @error('purpose')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
            </div>
            <div>
                <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Channel</div>
                <select wire:model="channelType" class="dot-input">
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="push">Push</option>
                    <option value="slack">Slack</option>
                </select>
            </div>
        </div>
        <button wire:click="generate" class="dot-btn dot-btn-primary" style="font-size:12px;" wire:loading.attr="disabled">
            <span wire:loading.remove>Generate with AI</span>
            <span wire:loading>Generating...</span>
        </button>
    </div>

    @if($generatedBody)
    <div style="margin-bottom:1rem;">
        <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Template Name</div>
        <input wire:model="templateName" type="text" class="dot-input" placeholder="Name this template..." />
    </div>
    @if($generatedSubject)
    <div style="font-size:12px;color:#71717a;margin-bottom:0.5rem;font-family:'JetBrains Mono',monospace;">Subject: {{ $generatedSubject }}</div>
    @endif
    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:8px;padding:0.75rem;font-size:12px;color:#a1a1aa;white-space:pre-line;margin-bottom:0.75rem;">{{ $generatedBody }}</div>
    <button wire:click="saveTemplate" class="dot-btn dot-btn-primary" style="font-size:12px;">Save Template</button>
    @endif
    @endif

    @if($this->templates->isEmpty())
        <div style="text-align:center;padding:1.5rem 0;">
            <p style="font-size:0.8rem;color:#52525b;margin:0;">No templates yet. Use AI to generate your first one.</p>
        </div>
    @else
        <div style="display:grid;gap:0.4rem;">
            @foreach($this->templates as $tpl)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:0.65rem 0.75rem;background:rgba(255,255,255,0.03);border-radius:8px;">
                <div>
                    <div style="font-size:12px;font-weight:600;color:#d4d4d8;">{{ $tpl->name }}</div>
                    <div style="font-size:11px;color:#52525b;">{{ ucfirst($tpl->channel_type ?? 'any') }} · Updated {{ $tpl->updated_at->diffForHumans() }}</div>
                </div>
                <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:100px;background:rgba(251,146,60,0.1);color:#fb923c;">{{ $tpl->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            @endforeach
        </div>
    @endif
</div>
