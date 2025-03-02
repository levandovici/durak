using UnityEngine;
using UnityEngine.UI;
using System.Collections.Generic;

public class UIManager : MonoBehaviour
{
    // References to UI elements in the scene
    [Header("UI Elements")]
    [SerializeField] private Transform handContainer; // Parent for player hand cards
    [SerializeField] private Transform tableContainer; // Parent for table cards
    [SerializeField] private Text turnIndicatorText; // Text to show whose turn it is
    [SerializeField] private GameObject cardPrefab; // Prefab for UI card (Image + Button)
    [SerializeField] private Text trumpSuitText; // Optional: Display trump suit

    // References to game logic
    [SerializeField] private GameManager gameManager;

    private List<GameObject> handCards = new List<GameObject>(); // Track instantiated hand cards
    private List<GameObject> tableCards = new List<GameObject>(); // Track instantiated table cards

    // Setup UI based on game state
    public void SetupUI(List<Player> players, List<Card> table, string trumpSuit, int currentPlayerId)
    {
        // Clear existing UI elements
        ClearHand();
        ClearTable();

        // Update player hand (only show local player's hand)
        Player localPlayer = players.Find(p => p.Id == gameManager.PlayerId || (gameManager.IsSinglePlayer && p.Id == 1));
        if (localPlayer != null)
        {
            foreach (Card card in localPlayer.Hand)
            {
                GameObject cardObj = Instantiate(cardPrefab, handContainer);
                Image cardImage = cardObj.GetComponent<Image>();
                cardImage.sprite = card.spriteRenderer.sprite;
                Button cardButton = cardObj.GetComponent<Button>();
                cardButton.onClick.AddListener(() => gameManager.PlayCard(card));
                handCards.Add(cardObj);
            }
        }

        // Update table
        foreach (Card card in table)
        {
            GameObject cardObj = Instantiate(cardPrefab, tableContainer);
            Image cardImage = cardObj.GetComponent<Image>();
            cardImage.sprite = card.spriteRenderer.sprite;
            tableCards.Add(cardObj);
        }

        // Update turn indicator
        Player currentPlayer = players.Find(p => p.IsTurn);
        turnIndicatorText.text = currentPlayer != null ? $"Turn: Player {currentPlayer.Id}" : "Turn: Unknown";

        // Update trump suit (optional)
        if (trumpSuitText != null)
        {
            trumpSuitText.text = $"Trump: {trumpSuit}";
        }
    }

    // Clear hand UI
    private void ClearHand()
    {
        foreach (GameObject card in handCards)
        {
            Destroy(card);
        }
        handCards.Clear();
    }

    // Clear table UI
    private void ClearTable()
    {
        foreach (GameObject card in tableCards)
        {
            Destroy(card);
        }
        tableCards.Clear();
    }

    // Properties to access GameManager data (used in GameManager)
    public int PlayerId => gameManager.PlayerId;
    public bool IsSinglePlayer => gameManager.IsSinglePlayer;
}
