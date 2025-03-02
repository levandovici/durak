using UnityEngine;
using UnityEngine.Networking;
using System.Collections;
using System.Collections.Generic;

public class GameManager : MonoBehaviour
{
    public Deck deck;
    private int gameId, playerId;
    private string sessionId;
    private bool isSinglePlayer;
    private List<Player> players = new List<Player>();
    private string trumpSuit;
    private List<Card> table = new List<Card>();
    private bool multiplayer = false;

    void Start()
    {
        if (multiplayer)
        {
            StartMultiplayer("Player");
        }
        else
        {
            StartSinglePlayer("Player");
        }
    }

    void StartSinglePlayer(string playerName)
    {
        isSinglePlayer = true;
        deck.GenerateDeck();
        trumpSuit = deck.Cards[deck.Cards.Count - 1].Suit;

        Player human = new Player(1, playerName);
        AIPlayer ai = new AIPlayer(2, "AI");
        players.Add(human);
        players.Add(ai);

        for (int i = 0; i < 6; i++)
        {
            human.AddCard(deck.DrawCard());
            ai.AddCard(deck.DrawCard());
        }
        human.IsTurn = true;
        UpdateUI();
    }

    public void StartMultiplayer(string username)
    {
        isSinglePlayer = false;
        StartCoroutine(JoinGame(username));
    }

    IEnumerator JoinGame(string username)
    {
        WWWForm form = new WWWForm();
        form.AddField("username", username);
        using (UnityWebRequest www = UnityWebRequest.Post("https://cloud.limonadoent.com/durak/join_game.php", form))
        {
            yield return www.SendWebRequest();
            if (www.result == UnityWebRequest.Result.Success)
            {
                var response = JsonUtility.FromJson<JoinResponse>(www.downloadHandler.text);
                gameId = response.game_id;
                playerId = response.player_id;
                sessionId = response.session_id;
                deck.GenerateDeck();
                StartCoroutine(PollGameState());
            }
            else
            {
                Debug.LogError("Join failed: " + www.error);
            }
        }
    }

    IEnumerator PollGameState()
    {
        while (true)
        {
            WWWForm form = new WWWForm();
            form.AddField("game_id", gameId);
            form.AddField("player_id", playerId);
            form.AddField("session_id", sessionId);
            using (UnityWebRequest www = UnityWebRequest.Post("https://cloud.limonadoent.com/durak/get_game_state.php", form))
            {
                yield return www.SendWebRequest();
                if (www.result == UnityWebRequest.Result.Success)
                {
                    var state = JsonUtility.FromJson<GameStateResponse>(www.downloadHandler.text);
                    if (state.status == "active" && players.Count == 0)
                        InitializeMultiplayer(state);
                    UpdateMultiplayer(state);
                }
            }
            yield return new WaitForSeconds(2f);
        }
    }

    void InitializeMultiplayer(GameStateResponse state)
    {
        trumpSuit = state.trump_suit;
        foreach (var p in state.players)
        {
            Player player = new Player(p.player_id, "Player" + p.player_id);
            players.Add(player);
            if (p.player_id == playerId)
            {
                for (int i = 0; i < 6; i++)
                    player.AddCard(deck.DrawCard());
            }
        }
        StartCoroutine(SyncInitialState());
    }

    IEnumerator SyncInitialState()
    {
        Player localPlayer = players.Find(p => p.Id == playerId);
        WWWForm form = new WWWForm();
        form.AddField("game_id", gameId);
        form.AddField("player_id", playerId);
        form.AddField("session_id", sessionId);
        form.AddField("card", JsonUtility.ToJson(new CardList { cards = localPlayer.Hand.ConvertAll(c => new CardData { suit = c.Suit, rank = c.Rank }) }));
        using (UnityWebRequest www = UnityWebRequest.Post("https://cloud.limonadoent.com/durak/play_card.php", form))
        {
            yield return www.SendWebRequest();
        }
    }

    public void PlayCard(Card card)
    {
        if (isSinglePlayer)
        {
            PlaySinglePlayerCard(card);
        }
        else
        {
            StartCoroutine(SendCard(card));
        }
    }

    void PlaySinglePlayerCard(Card card)
    {
        Player human = players[0];
        AIPlayer ai = (AIPlayer)players[1];
        if (human.IsTurn)
        {
            table.Add(card);
            human.Hand.Remove(card);
            Card aiCard = ai.PlayCard(card, trumpSuit);
            if (aiCard == null) // AI takes card
            {
                ai.AddCard(card);
                table.Clear();
                human.IsTurn = true;
            }
            else
            {
                table.Add(aiCard);
                human.IsTurn = aiCard.Rank == card.Rank; // Redirect
                ai.IsTurn = !human.IsTurn;
            }
        }
        UpdateUI();
    }

    IEnumerator SendCard(Card card)
    {
        WWWForm form = new WWWForm();
        form.AddField("game_id", gameId);
        form.AddField("player_id", playerId);
        form.AddField("session_id", sessionId);
        form.AddField("card", JsonUtility.ToJson(new CardData { suit = card.Suit, rank = card.Rank }));
        using (UnityWebRequest www = UnityWebRequest.Post("https://cloud.limonadoent.com/durak/play_card.php", form))
        {
            yield return www.SendWebRequest();
            if (www.result == UnityWebRequest.Result.Success)
            {
                var response = JsonUtility.FromJson<PlayResponse>(www.downloadHandler.text);
                if (response.success)
                {
                    players.Find(p => p.Id == playerId).Hand.Remove(card);
                    Debug.Log(response.redirected ? "Card redirected!" : "Card played!");
                }
                else
                {
                    Debug.LogError("Move failed: " + response.error);
                }
            }
            else
            {
                Debug.LogError("Play failed: " + www.error);
            }
        }
    }

    void UpdateMultiplayer(GameStateResponse state)
    {
        foreach (var s in state.state)
        {
            var player = players.Find(p => p.Id == s.player_id);
            if (player != null)
            {
                player.IsTurn = s.is_turn;
                if (s.player_id != playerId)
                {
                    var tableCards = JsonUtility.FromJson<CardList>(s.table);
                    table = new List<Card>(); // Reset table
                    foreach (var c in tableCards.cards)
                    {
                        Card card = deck.Cards.Find(card => card.Suit == c.suit && card.Rank == c.rank);
                        if (card != null) table.Add(card);
                    }
                }
            }
        }
        UpdateUI();
    }

    void UpdateUI()
    {
        Debug.Log("UI Update: Player " + (players.Find(p => p.IsTurn)?.Id ?? 0) + "'s turn");
        // Implement UI: Show player's hand, table cards, and turn indicator
    }
}

[System.Serializable]
public class JoinResponse
{
    public int game_id, player_id;
    public string session_id;
    public string error;
}

[System.Serializable]
public class GameStateResponse
{
    public string status, trump_suit;
    public PlayerOrder[] players;
    public StateEntry[] state;
    public string error;
}

[System.Serializable]
public class PlayerOrder
{
    public int player_id, turn_order;
}

[System.Serializable]
public class StateEntry
{
    public int player_id;
    public string hand, table;
    public bool is_turn;
}

[System.Serializable]
public class CardData
{
    public string suit, rank;
}

[System.Serializable]
public class CardList
{
    public List<CardData> cards;
}

[System.Serializable]
public class PlayResponse
{
    public bool success, redirected;
    public string error;
}