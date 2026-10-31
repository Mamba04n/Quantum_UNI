@if($selectedTracker)
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h4 class="text-sm font-black text-slate-800">Timeline del tracker</h4>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                        {{ $selectedTracker->tracking_code }} · {{ $selectedTracker->current_phase }} · {{ $selectedTracker->current_status }}
                    </p>
                </div>
                <span class="text-[10px] font-black bg-slate-100 text-slate-600 rounded-full px-2 py-1">{{ $selectedTracker->activities->count() }} actividades</span>
            </div>

            <div class="relative border-l-2 border-slate-200 ml-3 space-y-4">
                @forelse($selectedTracker->activities as $activity)
                    <div class="relative pl-5">
                        <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full bg-white border-4 border-slate-800"></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black text-slate-800">{{ str_replace('_', ' ', $activity->action) }}</p>
                                    <p class="text-[11px] text-slate-600 mt-1">{{ $activity->description }}</p>
                                </div>
                                <span class="text-[9px] font-bold text-slate-400 whitespace-nowrap">{{ $activity->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-[9px] font-black uppercase tracking-wider text-slate-400">
                                <span>{{ $activity->phase }}</span>
                                @if($activity->old_status || $activity->new_status)
                                    <span>{{ $activity->old_status ?: '-' }} -> {{ $activity->new_status ?: '-' }}</span>
                                @endif
                                <span>{{ $activity->user->name ?? 'Sistema' }}</span>
                            </div>

                            @if($activity->attachments->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($activity->attachments as $attachment)
                                        @if($attachment->is_image)
                                            <a href="{{ route('tracker.attachments.show', $attachment) }}" target="_blank">
                                                <img src="{{ route('tracker.attachments.show', $attachment) }}" class="h-14 w-14 object-cover rounded-lg border border-slate-200" loading="lazy" alt="{{ $attachment->file_name }}">
                                            </a>
                                        @else
                                            <a href="{{ route('tracker.attachments.show', $attachment) }}" target="_blank" class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-[10px] font-bold text-slate-600">{{ $attachment->file_name }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @if($activity->comments->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach($activity->comments as $comment)
                                        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2">
                                            <p class="text-[10px] text-slate-700">{{ $comment->comment }}</p>
                                            <p class="text-[9px] text-slate-400 mt-1">{{ $comment->user->name ?? 'Sistema' }} · {{ $comment->visibility }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="pl-5 text-xs text-slate-400 font-bold">Todavia no hay actividades en este tracker.</div>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-5 space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <h4 class="text-xs font-black text-slate-700 mb-3 uppercase tracking-widest">Documentos y Adjuntos</h4>
                <div class="space-y-2">
                    @forelse($selectedTracker->attachments as $attachment)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-black text-slate-700">{{ $attachment->file_name }}</p>
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                                        {{ $attachment->phase }} · {{ $attachment->created_at->format('d/m/Y H:i') }} · {{ $attachment->generator->name ?? $attachment->uploader->name ?? 'Sistema' }}
                                    </p>
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <a href="{{ route('tracker.attachments.show', $attachment) }}" target="_blank" class="text-[10px] font-black text-slate-700 hover:text-black">Ver</a>
                                    <a href="{{ route('tracker.attachments.show', ['attachment' => $attachment, 'download' => 1]) }}" class="text-[10px] font-black text-slate-700 hover:text-black">Descargar</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-[11px] font-bold text-slate-400">No hay documentos ni adjuntos en este tracker.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
@endif
