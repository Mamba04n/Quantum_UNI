<?php

namespace App\Livewire\Admin;

use App\Models\InternalMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InternalChatDropdown extends Component
{
    public $lastMessageId = 0;

    public function mount()
    {
        $latest = InternalMessage::where('receiver_id', Auth::id())->latest('id')->first();
        $this->lastMessageId = $latest ? $latest->id : 0;
    }

    public function checkNewMessages()
    {
        $newMessages = InternalMessage::with('user')
            ->where('receiver_id', Auth::id())
            ->where('id', '>', $this->lastMessageId)
            ->get();
            
        if ($newMessages->isNotEmpty()) {
            $this->lastMessageId = $newMessages->max('id');
            
            foreach ($newMessages as $msg) {
                // Si el mensaje aún no ha sido leído (para no notificar cosas leídas en otra pestaña)
                if (!$msg->read_at) {
                    $this->dispatch('notify-new-message', [
                        'title' => 'Nuevo mensaje de ' . $msg->user->name, 
                        'body' => $msg->message
                    ]);
                }
            }
        }
    }

    public function getConversationsProperty()
    {
        $userId = Auth::id();
        
        // Get the latest 100 messages involving the user
        $messages = InternalMessage::with(['user', 'receiver'])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('receiver_id', $userId);
            })
            ->latest()
            ->take(100)
            ->get();
            
        $conversations = collect();
        
        foreach ($messages as $msg) {
            $otherUserId = $msg->user_id === $userId ? $msg->receiver_id : $msg->user_id;
            // Ignore global chat messages for the 1-on-1 dropdown
            if (!$otherUserId) continue; 
            
            if (!$conversations->has($otherUserId)) {
                $otherUser = $msg->user_id === $userId ? $msg->receiver : $msg->user;
                $conversations->put($otherUserId, [
                    'user' => $otherUser,
                    'latest_message' => $msg,
                ]);
            }
        }

        return $conversations->take(5);
    }

    public function getUnreadTotalProperty()
    {
        return InternalMessage::where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->count();
    }

    public function render()
    {
        return view('livewire.admin.internal-chat-dropdown');
    }
}
