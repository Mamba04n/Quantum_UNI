<?php

namespace App\Livewire\Admin;

use App\Models\InternalMessage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InternalChat extends Component
{
    public $selectedUserId = null;
    public $newMessage = '';
    
    public $searchUser = '';
    public $showNewChatModal = false;

    protected $queryString = ['selectedUserId' => ['as' => 'user']];

    public function mount()
    {
        if ($this->selectedUserId) {
            $this->selectUser($this->selectedUserId);
        }
    }

    public function selectUser($userId)
    {
        $this->selectedUserId = $userId;
        $this->showNewChatModal = false;
        $this->markAsRead();
    }

    public function markAsRead()
    {
        if ($this->selectedUserId) {
            InternalMessage::where('receiver_id', Auth::id())
                ->where('user_id', $this->selectedUserId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }

    public function sendMessage()
    {
        $this->validate([
            'newMessage' => 'required|string|max:2000',
            'selectedUserId' => 'required|exists:users,id',
        ]);

        InternalMessage::create([
            'user_id' => Auth::id(),
            'receiver_id' => $this->selectedUserId,
            'message' => $this->newMessage,
        ]);

        $this->newMessage = '';
        $this->markAsRead();
    }

    public function getAvailableUsersProperty()
    {
        $userId = Auth::id();
        $query = User::whereHas('roles', function($q){
            $q->where('slug', '!=', 'cliente_tienda');
        })->where('id', '!=', $userId);

        if ($this->searchUser) {
            $query->where('name', 'like', '%' . $this->searchUser . '%');
        }

        return $query->take(20)->get();
    }

    public function getContactsProperty()
    {
        $userId = Auth::id();
        
        // Obtenemos los IDs de usuarios con los que hemos chateado
        $chattedWithIds = InternalMessage::where('user_id', $userId)
            ->pluck('receiver_id')
            ->merge(InternalMessage::where('receiver_id', $userId)->pluck('user_id'))
            ->unique()
            ->filter();

        // Agregamos el selectedUserId a la lista aunque no hayamos chateado todavia
        if ($this->selectedUserId && !$chattedWithIds->contains($this->selectedUserId)) {
            $chattedWithIds->push($this->selectedUserId);
        }

        $users = User::whereIn('id', $chattedWithIds)->get();
        
        $contacts = [];
        
        foreach($users as $user) {
            $lastMsg = InternalMessage::where(function($q) use ($userId, $user) {
                    $q->where('user_id', $userId)->where('receiver_id', $user->id);
                })
                ->orWhere(function($q) use ($userId, $user) {
                    $q->where('user_id', $user->id)->where('receiver_id', $userId);
                })
                ->latest()
                ->first();
                    
            $unread = InternalMessage::where('user_id', $user->id)
                ->where('receiver_id', $userId)
                ->whereNull('read_at')
                ->count();
                
            $contacts[] = [
                'user' => $user,
                'latest_message' => $lastMsg,
                'unread_count' => $unread,
                'last_time' => $lastMsg ? $lastMsg->created_at->timestamp : 0,
            ];
        }

        // Sort by latest message time
        usort($contacts, function($a, $b) {
            return $b['last_time'] <=> $a['last_time'];
        });

        return collect($contacts);
    }

    public function getActiveConversationProperty()
    {
        if (!$this->selectedUserId) return null;
        
        return InternalMessage::with(['user', 'receiver'])
            ->where(function($q) {
                $q->where('user_id', Auth::id())->where('receiver_id', $this->selectedUserId);
            })
            ->orWhere(function($q) {
                $q->where('user_id', $this->selectedUserId)->where('receiver_id', Auth::id());
            })
            ->latest()
            ->take(100)
            ->get()
            ->reverse()
            ->values();
    }

    public function render()
    {
        // Keep marking as read if polling updates the component while reading
        if ($this->selectedUserId) {
            $this->markAsRead();
        }

        return view('livewire.admin.internal-chat')
            ->layout('layouts.app');
    }
}
