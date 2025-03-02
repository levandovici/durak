using System.Collections.Generic;
using UnityEngine;

public class Deck : MonoBehaviour
{
    public GameObject cardPrefab;
    private List<Card> cards = new List<Card>();
    private string[] suits = { "Spades", "Hearts", "Diamonds", "Clubs" };
    private string[] ranks = { "2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K", "A" };



    public List<Card> Cards => cards;



    public void GenerateDeck()
    {
        cards.Clear();
        foreach (string suit in suits)
        {
            foreach (string rank in ranks)
            {
                GameObject cardObj = Instantiate(cardPrefab, transform);
                Card card = cardObj.GetComponent<Card>();
                Sprite cardSprite = Resources.Load<Sprite>($"{suit}_{rank}");
                card.SetCard(suit, rank, cardSprite);
                cards.Add(card);
            }
        }
        ShuffleDeck();
    }

    void ShuffleDeck()
    {
        for (int i = cards.Count - 1; i > 0; i--)
        {
            int randomIndex = Random.Range(0, i + 1);
            Card temp = cards[i];
            cards[i] = cards[randomIndex];
            cards[randomIndex] = temp;
        }
    }

    public Card DrawCard()
    {
        if (cards.Count > 0)
        {
            Card card = cards[0];
            cards.RemoveAt(0);
            return card;
        }
        return null;
    }
}